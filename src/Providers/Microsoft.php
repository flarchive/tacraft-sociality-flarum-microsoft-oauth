<?php

namespace TacraftSociality\MicrosoftOAuth\Providers;

use Flarum\Forum\Auth\Registration;
use FoF\OAuth\Provider;
use League\OAuth2\Client\Provider\AbstractProvider;
use TheNetworg\OAuth2\Client\Provider\Azure;
use TheNetworg\OAuth2\Client\Provider\AzureResourceOwner;

class Microsoft extends Provider
{
    public function name(): string
    {
        return 'microsoft';
    }

    public function link(): string
    {
        return 'https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade';
    }

    public function fields(): array
    {
        return [
            'client_id'     => 'required',
            'client_secret' => 'required',
            'tenant'        => '',
        ];
    }

    public function provider(string $redirectUri): AbstractProvider
    {
        return new Azure([
            'clientId'               => $this->getSetting('client_id'),
            'clientSecret'           => $this->getSetting('client_secret'),
            'redirectUri'            => $redirectUri,
            'defaultEndPointVersion' => Azure::ENDPOINT_VERSION_2_0,
            'scopes'                 => ['openid', 'profile', 'email'],
            'tenant'                 => $this->getSetting('tenant') ?: 'common',
        ]);
    }

    public function pkceEnabled(): bool
    {
        return true;
    }

    public function suggestions(Registration $registration, mixed $user, string $token): void
    {
        $email = $this->getEmail($user);
        $this->verifyEmail($email);

        if ($this->getProviderVerifiedEmail($user, $token) !== null) {
            $registration->provideTrustedEmail($email);
        } else {
            $registration->suggestEmail($email);
        }

        $preferredUsername = $user->getPreferredUsername() ?: $user->getUpn();
        $username = $preferredUsername
            ? (strstr($preferredUsername, '@', true) ?: $preferredUsername)
            : (string) $user->claim('name');

        $registration
            ->suggestUsername($username)
            ->setPayload($user->toArray());
    }

    public function getProviderVerifiedEmail(mixed $user, string $token): ?string
    {
        $claims = $user->toArray();
        $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($claims['xms_edov'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $tenant = strtolower($this->getSetting('tenant'));

        if (!$verified && $tenant !== '' && !in_array($tenant, ['common', 'organizations', 'consumers'], true)) {
            $verified = true;
        }

        return $verified ? $this->getEmail($user) : null;
    }

    private function getEmail(AzureResourceOwner $user): ?string
    {
        foreach (['email', 'preferred_username', 'upn'] as $claim) {
            $email = $user->claim($claim);

            if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }
}
