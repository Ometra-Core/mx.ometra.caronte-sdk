# caronte.app-token

Class: Ometra\Caronte\Http\Middleware\ValidateApplicationAccessToken

## Status

- Deprecated compatibility alias of `caronte.protected-api-token`.
- Use `caronte.protected-api-token` in new code.

## Behavior

- The implementation is the same as the protected API token middleware.
- It validates a bearer token, binds the protected API access context, and then continues the request.

## Debugging

Use the same steps as `caronte.protected-api-token`:

- verify `Authorization: Bearer` is present,
- confirm the token audience,
- confirm the signing secret,
- check issuer enforcement if enabled.