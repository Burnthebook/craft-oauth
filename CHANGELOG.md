# Release Notes for OAuth for Craft CMS

## 0.0.3

Handle non-OIDC OAuth2 providers by extracting user data from token response
- Support providers without a userInfoUrl by using access token payload for user info
- Covers OAuth2-only flows where user details are embedded in the token response
- Leaves OIDC-compatible providers unaffected

## 0.0.2
- Adds logging

## 0.0.1
- Initial release
