<?php

declare(strict_types=1);

namespace TacraftSociality\MicrosoftOAuth;

use Flarum\Extend;
use Flarum\Settings\SettingsRepositoryInterface;
use TacraftSociality\MicrosoftOAuth\Forum\Controllers\MicrosoftAuthController;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('forum'))
        ->get('/microsoft-oauth/callback', 'microsoft-oauth.callback', MicrosoftAuthController::class),

    (new Extend\Routes('api'))
        ->get('/microsoft-oauth/link', 'microsoft-oauth.link.show', Api\LinkStatusController::class)
        ->delete('/microsoft-oauth/link', 'microsoft-oauth.link.delete', Api\UnlinkController::class),

    (new Extend\Settings())
        ->default(Settings::CLIENT_ID, '')
        ->default(Settings::CLIENT_SECRET, '')
        ->default(Settings::TENANT, '')
        ->serializeToForum('microsoftOAuthEnabled', Settings::CLIENT_ID, function ($value): bool {
            if (! is_string($value) || trim($value) === '') {
                return false;
            }

            $secret = resolve(SettingsRepositoryInterface::class)->get(Settings::CLIENT_SECRET, '');

            return is_string($secret) && trim($secret) !== '';
        }),
];
