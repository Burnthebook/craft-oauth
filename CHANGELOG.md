# Release Notes for OAuth for Craft CMS


# 0.0.4
**Added**
- Custom OAuth Providers: You can now register and use custom OAuth providers by defining them in config/oauth.php with a providerClass option.
- Resource Owner Mapping: Custom providers can define their own ResourceOwner classes, allowing consistent access to getEmail(), getId(), and getName() methods.

**Improved**
- Authorization Flow: Improved PKCE support for custom providers using the GenericProvider.
- Callback Handling: Unified support for built-in and custom providers in the callback handling flow.

**Fixed**
- Access Token Handling: Fixed missing access_token issues for providers with non-standard responses.

## 0.0.3

Handle non-OIDC OAuth2 providers by extracting user data from token response
- Support providers without a userInfoUrl by using access token payload for user info
- Covers OAuth2-only flows where user details are embedded in the token response
- Leaves OIDC-compatible providers unaffected

## 0.0.2
- Adds logging

## 0.0.1
- Initial release
