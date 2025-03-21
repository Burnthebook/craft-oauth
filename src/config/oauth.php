<?php

/**
 * Craft OAuth config.php
 *
 * This file exists only as a template for the Craft OAuth settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'oauth.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */
use craft\helpers\App;

return [
    '*' => [
        'providers' => [
            [
                'provider' => 'github', // The provider name used for admin display purposes.
                'clientId' => App::env('GITHUB_CLIENT_ID'), // The client ID provided by the provider.
                'clientSecret' => App::env('GITHUB_CLIENT_SECRET'), // The client secret provided by the provider.
                'handle' => 'github', // The handle used to identify the provider in the plugin. (This is used for the login and callback url, e.g. /oauth/login/github - Must be unique.)
                'scopes' => 'read:user, user:email', // The scopes required by the provider.
                'authUrl' => 'https://github.com/login/oauth/access_token', // The URL to redirect the user to for authorization.
                'tokenUrl' => 'https://github.com/login/oauth/access_token', // The URL to exchange the authorization code for an access token.
                'userInfoUrl' => 'https://api.github.com/user', // The URL to get the user information.
            ],
            [
                'provider' => 'google',
                'clientId' => App::env('GOOGLE_CLIENT_ID'),
                'clientSecret' => App::env('GOOGLE_CLIENT_SECRET'),
                'handle' => 'google',
                'scopes' => 'email,profile',
                'authUrl' => 'https://accounts.google.com/o/oauth2/auth',
                'tokenUrl' => 'https://oauth2.googleapis.com/token',
                'userInfoUrl' => 'https://openidconnect.googleapis.com/v1/userinfo',
            ],
            [
                'provider' => 'facebook',
                'clientId' => App::env('FACEBOOK_CLIENT_ID'),
                'clientSecret' => App::env('FACEBOOK_CLIENT_SECRET'),
                'handle' => 'facebook',
                'scopes' => 'email',
                'authUrl' => 'https://www.facebook.com/v11.0/dialog/oauth',
                'tokenUrl' => 'https://graph.facebook.com/v11.0/oauth/access_token',
                'userInfoUrl' => 'https://graph.facebook.com/me?fields=id,name,email',
            ],
            [
                'provider' => 'instagram',
                'clientId' => App::env('INSTAGRAM_CLIENT_ID'),
                'clientSecret' => App::env('INSTAGRAM_CLIENT_SECRET'),
                'handle' => 'instagram',
                'scopes' => 'user_profile',
                'authUrl' => 'https://api.instagram.com/oauth/authorize',
                'tokenUrl' => 'https://api.instagram.com/oauth/access_token',
                'userInfoUrl' => 'https://graph.instagram.com/me?fields=id,username',
            ],
            [
                'provider' => 'linkedin',
                'clientId' => App::env('LINKEDIN_CLIENT_ID'),
                'clientSecret' => App::env('LINKEDIN_CLIENT_SECRET'),
                'handle' => 'linkedin',
                'scopes' => 'r_liteprofile, r_emailaddress',
                'authUrl' => 'https://www.linkedin.com/oauth/v2/authorization',
                'tokenUrl' => 'https://www.linkedin.com/oauth/v2/accessToken',
                'userInfoUrl' => 'https://api.linkedin.com/v2/me',
            ],
            [
                'provider' => 'custom',
                'clientId' => App::env('CUSTOM_ID'),
                'clientSecret' => App::env('CUSTOM_SECRET'),
                'handle' => 'custom',
                'scopes' => 'read',
                'authUrl' => 'https://example.com/oauth/authorize',
                'tokenUrl' => 'https://example.com/oauth/token',
                'userInfoUrl' => 'https://example.com/api/userinfo',
            ],
        ],
    ],
];