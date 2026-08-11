# Middleware: BlockLoginRouteWhenDisabled

**Alias**: `caronte.block-login-route-when-disabled`

## Purpose

Prevents access to the login form route when the login form is provided by an external authentication service. This middleware blocks direct navigation to the built-in login page, redirecting requests to a configured external URL.

## Configuration

Enable this middleware in your route group when using delegated login:

```php
Route::middleware('caronte.block-login-route-when-disabled')
    ->group(function () {
        // Login routes
    });
```

## Behavior

- **When login form is external**: Redirects to the external login URL configured in `caronte.routes.login_url` (HTTP 302).
- **When login form is external but no URL configured**: Returns HTTP 404.
- **When login form is internal**: Allows normal request flow (no action taken).

## Related Configuration

- `caronte.routes.login_url` — external login URL destination
- `caronte.routes.login_enabled` — controls whether internal login routes are registered

## See Also

- [Routes Documentation](../routes-documentation.md)
- [Middleware Index](../middleware.md)
