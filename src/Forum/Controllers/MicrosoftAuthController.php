<?php

declare(strict_types=1);

namespace TacraftSociality\MicrosoftOAuth\Forum\Controllers;

use Flarum\Forum\Auth\Registration;
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\LoginProvider;
use Flarum\User\User;
use Illuminate\Session\Store;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TacraftSociality\MicrosoftOAuth\Settings;
use TheNetworg\OAuth2\Client\Provider\Azure;
use TheNetworg\OAuth2\Client\Provider\AzureResourceOwner;
use Throwable;

class MicrosoftAuthController implements RequestHandlerInterface
{
    private const PROVIDER = 'microsoft';

    private const SESSION_STATE = 'microsoft_oauth_state';

    private const SESSION_PKCE = 'microsoft_oauth_pkce';

    private const SESSION_RETURN_TO = 'microsoft_oauth_return_to';

    /**
     * Set when a logged-in user starts the account-linking flow.
     */
    private const SESSION_LINK = 'microsoft_oauth_link';

    /**
     * The user the Microsoft account should be linked to.
     */
    private const SESSION_LINK_USER = 'microsoft_oauth_link_user';

    public const ERROR_PARAM = '_flarum_microsoft_error';

    public const MARKER_PARAM = '_flarum_microsoft';

    public const LINKED_PARAM = '_flarum_microsoft_linked';

    private const SCOPES = ['openid', 'profile', 'email'];

    public function __construct(
        protected ResponseFactory $response,
        protected SettingsRepositoryInterface $settings,
        protected UrlGenerator $url,
        protected LoggerInterface $logger
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute('session');
        $query = $request->getQueryParams();
        $actor = RequestUtil::getActor($request);
        $linkRequested = $this->isLinking($query);

        $returnTo = $this->resolveReturnTo($request, $session);

        // A link flow may already be in progress: the Microsoft callback does
        // not carry the link parameter, only the stored session flag.
        $linkInProgress = $session->get(self::SESSION_LINK) === true;
        $linkUserId = $session->get(self::SESSION_LINK_USER);

        if ($linkRequested && ! $actor->exists) {
            $this->forgetFlow($session);

            return $this->errorResponse($returnTo, 'login_required');
        }

        if ($actor->exists && ! $linkRequested && ! $linkInProgress) {
            return new RedirectResponse($returnTo);
        }

        if (isset($query['error'])) {
            $this->forgetFlow($session);

            return $this->errorResponse($returnTo, 'cancelled');
        }

        $clientId = trim((string) $this->settings->get(Settings::CLIENT_ID, ''));
        $clientSecret = trim((string) $this->settings->get(Settings::CLIENT_SECRET, ''));

        if ($clientId === '' || $clientSecret === '') {
            return $this->errorResponse($returnTo, 'not_configured');
        }

        $provider = $this->provider($clientId, $clientSecret);
        $code = $query['code'] ?? null;

        if (! is_string($code) || $code === '') {
            if (isset($query['state']) || isset($query['usercancel'])) {
                $this->forgetFlow($session);

                return $this->errorResponse($returnTo, 'cancelled');
            }

            $state = bin2hex(random_bytes(32));
            $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $provider->setPkceCode($verifier);

            try {
                $authorizationUrl = $provider->getAuthorizationUrl([
                    'scope'                 => self::SCOPES,
                    'state'                 => $state,
                    'code_challenge'        => $challenge,
                    'code_challenge_method' => 'S256',
                ]);
            } catch (Throwable $e) {
                $this->logger->warning('[flarum-microsoft-oauth] authorization failed', [
                    'exception' => $e,
                ]);

                return $this->errorResponse($returnTo, 'network_error');
            }

            $session->put(self::SESSION_STATE, $state);
            $session->put(self::SESSION_PKCE, $verifier);
            $session->put(self::SESSION_RETURN_TO, $returnTo);

            // A fresh authorization request supersedes an abandoned link attempt.
            $session->forget(self::SESSION_LINK);
            $session->forget(self::SESSION_LINK_USER);

            if ($linkRequested) {
                $session->put(self::SESSION_LINK, true);
                $session->put(self::SESSION_LINK_USER, (int) $actor->id);
            }

            return new RedirectResponse($authorizationUrl);
        }

        $expectedState = $session->get(self::SESSION_STATE);
        $verifier = $session->get(self::SESSION_PKCE);
        $storedReturnTo = $session->get(self::SESSION_RETURN_TO);

        if (is_string($storedReturnTo) && $storedReturnTo !== '') {
            $returnTo = $storedReturnTo;
        }

        $this->forgetFlow($session);

        $state = $query['state'] ?? null;

        if (
            ! is_string($expectedState)
            || $expectedState === ''
            || ! is_string($state)
            || ! hash_equals($expectedState, $state)
        ) {
            return $this->errorResponse($returnTo, 'invalid_state');
        }

        if (is_string($verifier) && $verifier !== '') {
            $provider->setPkceCode($verifier);
        }

        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);
            $user = $provider->getResourceOwner($token);
        } catch (Throwable $e) {
            $this->logger->warning('[flarum-microsoft-oauth] token exchange failed', [
                'exception' => $e,
            ]);

            return $this->errorResponse($returnTo, 'provider_error');
        }

        if (! $user instanceof AzureResourceOwner) {
            return $this->errorResponse($returnTo, 'invalid_response');
        }

        $identifier = (string) $user->getId();

        if ($identifier === '') {
            return $this->errorResponse($returnTo, 'invalid_response');
        }

        // Account-linking flow: an authenticated user is adding Microsoft to
        // their existing account instead of logging in with it.
        if ($linkInProgress) {
            if (! $actor->exists) {
                return $this->errorResponse($returnTo, 'login_required');
            }

            if ($linkUserId === null || (int) $linkUserId === 0 || (int) $linkUserId !== (int) $actor->id) {
                return $this->errorResponse($returnTo, 'invalid_state');
            }

            return $this->linkAccount($actor, $identifier, $returnTo, $session);
        }

        $email = $this->email($user);

        if ($email === null) {
            return $this->errorResponse($returnTo, 'invalid_response');
        }

        $response = $this->response->make(
            self::PROVIDER,
            (string) $user->getId(),
            function (Registration $registration) use ($user, $email): void {
                if ($this->isTrustedEmail($user->toArray())) {
                    $registration->provideTrustedEmail($email);
                } else {
                    $registration->suggestEmail($email);
                }

                $username = $this->username($user);

                if ($username !== '') {
                    $registration->suggestUsername($username);
                }

                $registration->setPayload($user->toArray());
            },
            $this->markReturnTo($returnTo)
        );

        $session->regenerate(true);

        return $response;
    }

    /**
     * Attach the Microsoft account to the logged-in user.
     *
     * Linking an account that already belongs to somebody else is refused, and
     * linking a different Microsoft account replaces the previous binding.
     */
    private function linkAccount(User $actor, string $identifier, string $returnTo, Store $session): RedirectResponse
    {
        $existing = LoginProvider::query()
            ->where('provider', self::PROVIDER)
            ->where('identifier', $identifier)
            ->first();

        if ($existing !== null && (int) $existing->user_id !== (int) $actor->id) {
            return $this->errorResponse($returnTo, 'already_linked');
        }

        LoginProvider::query()
            ->where('provider', self::PROVIDER)
            ->where('user_id', $actor->id)
            ->where('identifier', '!=', $identifier)
            ->delete();

        $actor->loginProviders()->firstOrCreate([
            'provider' => self::PROVIDER,
            'identifier' => $identifier,
        ]);

        $session->regenerate(true);

        return new RedirectResponse($this->appendQuery($returnTo, self::LINKED_PARAM, '1'));
    }

    /**
     * Whether the request asks to link Microsoft to the current account.
     */
    private function isLinking(array $query): bool
    {
        foreach (['link', 'linkTo'] as $key) {
            $value = $query[$key] ?? null;

            if (! is_scalar($value)) {
                continue;
            }

            $value = (string) $value;

            if ($value === '' || $value === '0') {
                continue;
            }

            return true;
        }

        return false;
    }

    private function provider(string $clientId, string $clientSecret): Azure
    {
        $tenant = trim((string) $this->settings->get(Settings::TENANT, ''));

        return new Azure([
            'clientId'               => $clientId,
            'clientSecret'           => $clientSecret,
            'redirectUri'            => $this->url->to('forum')->route('microsoft-oauth.callback'),
            'defaultEndPointVersion' => Azure::ENDPOINT_VERSION_2_0,
            'scopes'                 => self::SCOPES,
            'tenant'                 => $tenant !== '' ? $tenant : 'common',
        ]);
    }

    private function resolveReturnTo(ServerRequestInterface $request, Store $session): string
    {
        $query = $request->getQueryParams();
        $candidate = $query['returnTo'] ?? null;

        if (! is_string($candidate) || $candidate === '') {
            $candidate = $session->get(self::SESSION_RETURN_TO);
        }

        if (is_string($candidate) && self::isSafeReturnPath($candidate)) {
            return $candidate;
        }

        $referer = $request->getHeaderLine('Referer');

        if ($referer !== '') {
            $path = parse_url($referer, PHP_URL_PATH);

            if (is_string($path) && self::isSafeReturnPath($path)) {
                return $path;
            }
        }

        return '/';
    }

    private static function isSafeReturnPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ! str_contains($path, '://')
            && ! str_contains($path, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }

    private function markReturnTo(string $returnTo): string
    {
        return $this->appendQuery($returnTo, self::MARKER_PARAM, '1');
    }

    private function appendQuery(string $url, string $key, string $value): string
    {
        [$path, $hash] = array_pad(explode('#', $url, 2), 2, null);

        $separator = str_contains($path, '?') ? '&' : '?';
        $marked = $path.$separator.rawurlencode($key).'='.rawurlencode($value);

        return $hash === null ? $marked : $marked.'#'.$hash;
    }

    private function errorResponse(string $returnTo, string $reason): RedirectResponse
    {
        [$path, $hash] = array_pad(explode('#', $returnTo, 2), 2, null);

        $separator = str_contains($path, '?') ? '&' : '?';
        $url = $path.$separator.self::ERROR_PARAM.'='.rawurlencode($reason);

        return new RedirectResponse($hash === null ? $url : $url.'#'.$hash);
    }

    private function forgetFlow(Store $session): void
    {
        $session->forget(self::SESSION_STATE);
        $session->forget(self::SESSION_PKCE);
        $session->forget(self::SESSION_RETURN_TO);
        $session->forget(self::SESSION_LINK);
        $session->forget(self::SESSION_LINK_USER);
    }

    private function email(AzureResourceOwner $user): ?string
    {
        foreach (['email', 'preferred_username', 'upn'] as $claim) {
            $email = $user->claim($claim);

            if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    private function username(AzureResourceOwner $user): string
    {
        $preferred = $user->getPreferredUsername() ?: $user->getUpn();

        if (is_string($preferred) && $preferred !== '') {
            return strstr($preferred, '@', true) ?: $preferred;
        }

        $name = $user->claim('name');

        return is_string($name) ? $name : '';
    }

    private function isTrustedEmail(array $claims): bool
    {
        $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($claims['xms_edov'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($verified) {
            return true;
        }

        $tenant = strtolower(trim((string) $this->settings->get(Settings::TENANT, '')));

        return $tenant !== '' && ! in_array($tenant, ['common', 'organizations', 'consumers'], true);
    }
}
