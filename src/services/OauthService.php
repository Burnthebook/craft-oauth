<?php

namespace burnthebook\craftoauth\services;

use Craft;
use yii\base\Component;
use burnthebook\craftoauth\OAuth;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\LinkedIn;
use League\OAuth2\Client\Provider\Instagram;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class OauthService extends Component
{
    /**
     * Retrieves the provider for the given provider handle.
     *
     * @param string $providerHandle The handle of the provider to retrieve.
     * @return AbstractProvider|null The provider for the given handle, or null if no matching handle is found.
    */
    public function getProvider(string $providerHandle): ?AbstractProvider
    {
        $settings = OAuth::getInstance()->getEffectiveSettings();
        $providers = $settings->providers;

        foreach ($providers as $config) {
            // Match the row where the handle equals the requested providerHandle
            if (($config['handle'] ?? '') === $providerHandle) {
                Craft::info("Retrieving provider: {$providerHandle}", 'oauth');
                $providerType = strtolower($config['provider'] ?? 'custom');
                $redirectUri = Craft::$app->getSites()->getCurrentSite()->getBaseUrl() . 'oauth/callback/' . $providerHandle;
                
                Craft::info("OAuth provider clientId: " . $config['clientId'], 'oauth');
                Craft::info("OAuth provider redirectUri: " . $redirectUri, 'oauth');
                
                switch ($providerType) {
                    case 'google':
                        return new Google([
                            'clientId' => $config['clientId'],
                            'clientSecret' => $config['clientSecret'],
                            'redirectUri' => $redirectUri,
                        ]);
                    case 'github':
                        return new Github([
                            'clientId' => $config['clientId'],
                            'clientSecret' => $config['clientSecret'],
                            'redirectUri' => $redirectUri,
                        ]);
                    case 'facebook':
                        return new Facebook([
                            'clientId' => $config['clientId'],
                            'clientSecret' => $config['clientSecret'],
                            'redirectUri' => $redirectUri,
                        ]);
                    case 'instagram':
                        return new Instagram([
                            'clientId' => $config['clientId'],
                            'clientSecret' => $config['clientSecret'],
                            'redirectUri' => $redirectUri,
                        ]);
                    case 'linkedin':
                        return new LinkedIn([
                            'clientId' => $config['clientId'],
                            'clientSecret' => $config['clientSecret'],
                            'redirectUri' => $redirectUri,
                        ]);
                    case 'custom':
                    default:
                        // Validate that custom URLs are present
                        return new GenericProvider([
                            'clientId'                => $config['clientId'],
                            'clientSecret'            => $config['clientSecret'],
                            'redirectUri'             => $redirectUri,
                            'urlAuthorize'            => $config['authUrl'],
                            'urlAccessToken'          => $config['tokenUrl'],
                            'urlResourceOwnerDetails' => $config['userInfoUrl'],
                        ]);
                }
            }
        }

        Craft::warning("Provider not found: {$providerHandle}", 'oauth');
        return null; // No matching handle found
    }

    /**
     * Returns the authorization URL for the specified provider.
     *
     * @param string $providerHandle The handle of the provider to get the authorization URL for.
     * @return string|null The authorization URL, or null if the provider is not found.
    */
    public function getAuthorizationUrl(string $providerHandle): ?string
    {
        $provider = $this->getProvider($providerHandle);
        $settings = OAuth::getInstance()->getSettings();
        $providers = $settings->providers;

        $scopes = [];

        foreach ($providers as $config) {
            if (($config['handle'] ?? '') === $providerHandle) {
                $enablePkce = (bool) ($config['pkce'] ?? false);

                if (!empty($config['scopes'])) {
                    $scopes = array_map('trim', explode(',', $config['scopes']));
                } else {
                    // Default scopes based on provider type
                    $providerType = strtolower($config['provider'] ?? 'custom');

                    switch ($providerType) {
                        case 'google':
                            $scopes = ['email', 'profile'];
                            break;
                        case 'github':
                            $scopes = ['read:user', 'user:email'];
                            break;
                        case 'facebook':
                            $scopes = ['email'];
                            break;
                        case 'instagram':
                            $scopes = ['user_profile'];
                            break;
                        case 'linkedin':
                            $scopes = ['r_liteprofile', 'r_emailaddress'];
                            break;
                        default:
                            $scopes = [];
                    }
                }
                break;
            }
        }

        if ($provider) {
            if ($provider instanceof GenericProvider && $enablePkce) {
                // Generate PKCE Code Verifier + Challenge
                $codeVerifier = bin2hex(random_bytes(64));
                $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        
                // Store code_verifier in session
                Craft::$app->getSession()->set('pkceCodeVerifier', $codeVerifier);
        
                // Get auth URL with PKCE params
                $authUrl = $provider->getAuthorizationUrl([
                    'scope' => $scopes,
                    'code_challenge' => $codeChallenge,
                    'code_challenge_method' => 'S256',
                    'approval_prompt' => '', // approval_prompt is a google specific oauth param that league/oauth-client injects. We do not need it for GenericProvider
                ]);

                // Clean up URL: remove empty approval_prompt param
                $authUrl = preg_replace('/([&?])approval_prompt=&?/', '$1', $authUrl);

                // Also clean up any trailing ? or &
                $authUrl = rtrim($authUrl, '&?');

            } else {
                // Standard League provider (no PKCE needed)
                $authUrl = $provider->getAuthorizationUrl([
                    'scope' => $scopes
                ]);
            }

            Craft::info("Authorization params: " . json_encode([
                'scope' => $scopes,
                'code_challenge' => $codeChallenge ?? null,
                'code_challenge_method' => 'S256',
            ]), 'oauth');

            Craft::$app->getSession()->set('oauthState', $provider->getState());
            
            Craft::info('Final redirect URL: ' . $authUrl, 'oauth');

            return $authUrl;
        }

        return null;
    }

    /**
     * Handles the callback for an OAuth provider.
     *
     * @param string $providerHandle The handle of the provider to handle the callback for.
     * @return array|null An array containing the access token and user information, or null if the callback fails.
     * @throws \Exception If the provider is unknown or if the OAuth state is invalid.
    */
    public function handleCallback(string $providerHandle): ?array
    {
        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        $provider = $this->getProvider($providerHandle);
        

        if (!$provider) {
            Craft::error("Unknown provider during callback: {$providerHandle}", 'oauth');
            throw new \Exception("Unknown provider: $providerHandle");
        }

        $storedState = $session->get('oauthState');
        $receivedState = $request->getParam('state');

        if (!$receivedState || $receivedState !== $storedState) {
            Craft::error('Invalid OAuth state during callback.', 'oauth');
            throw new \Exception('Invalid OAuth state.');
        }

        try {
            $tokenOptions = [
                'code' => $request->getParam('code'),
            ];
    
            // Add PKCE code_verifier if using GenericProvider
            if ($provider instanceof GenericProvider) {
                $codeVerifier = $session->get('pkceCodeVerifier');
                if ($codeVerifier) {
                    $tokenOptions['code_verifier'] = $codeVerifier;
                }
            }
    
            $settings = OAuth::getInstance()->getEffectiveSettings();
            $providers = $settings->providers;
            $config = null;
            
            foreach ($providers as $providerConfig) {
                if (($providerConfig['handle'] ?? '') === $providerHandle) {
                    $config = $providerConfig;
                    break;
                }
            }
            
            $redirectUri = Craft::$app->getSites()->getCurrentSite()->getBaseUrl() . 'oauth/callback/' . $providerHandle;
            
            Craft::info("OAuth token request options: " . json_encode($tokenOptions), 'oauth');
            Craft::info("OAuth provider clientId: " . $config['clientId'], 'oauth');
            Craft::info("OAuth provider redirectUri: " . $redirectUri, 'oauth');
            Craft::info("OAuth provider token URL: " . $config['tokenUrl'], 'oauth');

            Craft::info("Attempting to retrieve access token for provider: {$providerHandle}", 'oauth');
            $accessToken = $provider->getAccessToken('authorization_code', $tokenOptions);
            Craft::info("Access token retrieved successfully for provider: {$providerHandle}. Token: " . json_encode($accessToken), 'oauth');

            if (empty($config['userInfoUrl'])) {
                // No userInfoUrl, use token response for user data
                $userData = $accessToken->getValues();
                Craft::info("User information extracted from token for provider: {$providerHandle}. User: " . json_encode($userData), 'oauth');

                return [
                    'provider' => $providerHandle,
                    'token' => $accessToken,
                    'user' => $userData,
                ];
            }

            // Default flow
            Craft::info("Attempting to retrieve user information for provider: {$providerHandle}", 'oauth');
            $user = $provider->getResourceOwner($accessToken);
            Craft::info("User information retrieved successfully for provider: {$providerHandle}. User: " . json_encode($user->toArray()), 'oauth');

            Craft::info("OAuth callback successful for provider: {$providerHandle}", 'oauth');
            return [
                'provider' => $providerHandle,
                'token' => $accessToken,
                'user' => $user,
            ];

        } catch (\Exception $e) {
            dd($e);
            Craft::error('OAuth callback failed: ' . $e->getMessage(), 'oauth');
            return null;
        }
    }
}
