# caronte.app-permissions

Class: Ometra\Caronte\Http\Middleware\ValidateApplicationAccessPermissions

## Status

- Deprecated compatibility alias of `caronte.protected-api-scopes`.
- Use `caronte.protected-api-scopes` in new code.

## Behavior

- The implementation is the same as the protected API scopes middleware.
- It reads the bound protected API access context and enforces the required scopes.

## Debugging

Use the same steps as `caronte.protected-api-scopes`:

- make sure the token middleware runs first,
- confirm the context is present,
- compare the requested scopes with the token scopes,
- check route ordering when scope checks fail.