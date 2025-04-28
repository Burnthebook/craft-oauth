# OAuth for Craft CMS

Adds OAuth Functionality to Craft CMS.

## Requirements

This plugin requires Craft CMS 5.6.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for "OAuth for Craft CMS". Then press "Install".

### With Composer

Open your terminal and run the following commands:

Go to the project directory

    cd /path/to/my-project.test

Tell Composer to load the plugin

    composer require burnthebook/craft-oauth

Tell Craft to install the plugin

    php craft plugin/install craft-oauth

## Usage

You can configure OAuth providers either:
- Through config/oauth.php, or
- Via the Craft Control Panel under Settings → OAuth.

The plugin currently supports connecting to:

| Provider | Required Settings |
| -------- | ----------------- |
| GitHub | Client ID, Client Secret |
| Google | Client ID, Client Secret |
| Facebook | Client ID, Client Secret |
| Instagram | Client ID, Client Secret |
| LinkedIn | Client ID, Client Secret |

For each provider, you can define:
- Scopes
- PKCE (Proof Key for Code Exchange) support
- Authorization URL, Token URL, and User Info URL (optional for custom providers)

When connecting a provider, the plugin will handle:
- Redirecting to the authorization URL
- Managing OAuth state
- Receiving and verifying the callback
- Fetching user profile information

After successful login, a user’s connected OAuth accounts will appear in the Craft Control Panel under: **Admin → Users → {User} → OAuth Accounts** as a table showing the linked providers and account IDs.

## Adding a Custom Provider

If you need to connect to a non-standard OAuth2 provider, you can define a Custom Provider.

1. Create a Provider class extending League\OAuth2\Client\Provider\AbstractProvider.
2. (Optionally) Create a custom ResourceOwner class implementing ResourceOwnerInterface to handle user data. (This is only really necessary if you provide user data as a response to your access token request, and do not have a userInfo endpoint.)
3. Define your provider in config/oauth.php like so:

```
return [
    'providers' => [
        [
            'handle' => 'yourprovider',
            'provider' => 'custom',
            'providerClass' => \modules\yourmodule\providers\YourProvider::class,
            'clientId' => 'YOUR_CLIENT_ID',
            'clientSecret' => 'YOUR_CLIENT_SECRET',
            'authUrl' => 'https://example.com/oauth/authorize',
            'tokenUrl' => 'https://example.com/oauth/token',
            'userInfoUrl' => 'https://example.com/oauth/userinfo', // Optional if handled by custom provider
            'scopes' => 'read,write',
            'pkce' => true,
        ],
    ],
];
```

> [!IMPORTANT]
> Custom providers must be configured in config/oauth.php (not via the Craft Settings UI).
> You must supply a valid providerClass which implements the necessary OAuth behavior.

The plugin will automatically use your custom provider when the user attempts to log in via OAuth.

## Example: Custom Provider Class

Here’s a basic example of a custom OAuth provider and resource owner you could use.

> [!NOTE]
> The below code is provided as an example only, will need tweaking to your implementation and we cannot assist with custom providers.
> This plugin uses [league/oauth2-client](https://oauth2-client.thephpleague.com/) under the hood and any custom provider must [conform to their standards.](https://oauth2-client.thephpleague.com/providers/implementing/)

### Provider Class

composer.json:

```json
"autoload": {
    "psr-4": {
        // Other modules here...
        "modules\\oauth\\providers\\": "modules/CustomOauthProviders/"
    }
},
```

Provider Class:

```php
<?php
// /modules/CustomOauthProviders/YourProvider.php
namespace modules\oauth\providers;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Grant\AbstractGrant;
use modules\yourmodule\resourceowners\YourResourceOwner;

class YourProvider extends GenericProvider
{
    protected function createAccessToken(array $response, AbstractGrant $grant)
    {
        // If your OAuth server returns a non-standard token response, you can adjust it here
        return parent::createAccessToken($response, $grant);
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        // Define the fields your OAuth server sends back
        return new YourResourceOwner($response);
    }

    protected function fetchResourceOwnerDetails(AccessToken $token)
    {
        // If your server doesn't use a separate userinfo URL,
        // you can fetch details manually here.
        return $token->getValues();
    }
}
```

Resource Owner Class:

```php
<?php
// /modules/CustomOauthProviders/YourResourceOwner.php

namespace modules\oauth\providers;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class YourResourceOwner implements ResourceOwnerInterface
{
    protected $response;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function getId()
    {
        return $this->response['id'] ?? null;
    }

    public function getEmail()
    {
        return $this->response['email'] ?? null;
    }

    public function getName()
    {
        return $this->response['name'] ?? null;
    }

    public function toArray()
    {
        return $this->response;
    }
}
```

> [!NOTE] 
> YourProvider must extend League\OAuth2\Client\Provider\AbstractProvider (or in most cases, GenericProvider).
> YourResourceOwner must implement ResourceOwnerInterface and provide the toArray() method.