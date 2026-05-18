# README (Root of the Project)

> This documentation follows the project's Coding Standards and PHPDoc Style Guide.

---

## Project Overview

`ometra/caronte-sdk` is a **Laravel package** that integrates any Laravel host application with the **Caronte** centralised authentication server. It handles:

- JWT user authentication (login, logout, 2FA, password recovery)
- Automatic token validation and renewal on every request
- Role-based access control tied to a central role registry
- Application API permission declaration and application-token middleware
- A ready-to-use management UI for users and roles
- A server-side provisioning wrapper for Caronte tenant provisioning
- Server-to-server inter-app communication via application tokens

**Primary audience:** Internal development teams adding Caronte authentication to a Laravel application.

---

## Project Type & Tech Summary

| Item                    | Value                                               |
| ----------------------- | --------------------------------------------------- |
| **Type**                | Laravel Package (library)                           |
| **PHP**                 | `^8.2`                                              |
| **Laravel**             | `^12.0`                                             |
| **JWT library**         | `lcobucci/jwt ^5.3` + `lcobucci/clock ^3.2`         |
| **Database**            | MySQL / any Laravel-supported driver (via host app) |
| **Cache**               | Host app cache (no package-level cache)             |
| **Queue**               | None (all requests are synchronous)                 |
| **External service**    | Caronte authentication server (HTTP API)            |
| **Optional dependency** | `inertiajs/inertia-laravel ^2.0`                    |

---

## Quick Start

1. **Install the package**

    ```bash
    composer require ometra/caronte-sdk
    ```

2. **Publish the configuration**

    ```bash
    php artisan vendor:publish --tag=caronte:config
    ```

3. **Add the three required environment variables**

    ```env
    CARONTE_URL=https://your-caronte-server.example.com
    CARONTE_APP_CN=your-app-canonical-name
    CARONTE_APP_SECRET=a-secret-at-least-32-characters-long
    CARONTE_TOKEN_REFRESH_LEEWAY_SECONDS=60
    ```

    Apps that belong to an internal application group can also share user tokens and app-to-app credentials:

    ```env
    CARONTE_APPLICATION_GROUP_ID=core-suite
    CARONTE_APPLICATION_GROUP_SECRET=a-group-secret-at-least-32-characters-long
    ```

4. **Run migrations** (creates local user cache tables)

    ```bash
    php artisan migrate
    ```

5. **Protect routes** with the provided middleware:

    ```php
    Route::middleware(['caronte.session', 'caronte.roles:admin'])->group(...);
    ```

6. **Sync your configured roles** with the Caronte server:

    ```bash
    php artisan caronte:roles:sync
    ```

7. **Declare API permissions** if external applications will consume your API:

    ```php
    'permissions' => [
        'invoices.read' => 'Read invoices',
        'invoices.write' => 'Write invoices',
    ],
    ```

    ```bash
    php artisan caronte:permissions:sync
    ```

8. **Protect external API routes** with application-token middleware:

    ```php
    Route::middleware(['caronte.app-token', 'caronte.app-permissions:invoices.read'])->get(...);
    ```

9. **Visit** the app-local management UI at `/caronte/management` (default).

## Token Types

- User JWTs authenticate humans and are checked by `caronte.session`.
- User JWTs are read from phase-2 top-level claims first: `sub`, `aud`, `jti`, `tenant_id`, `roles`, `metadata`, `app_id`, and `token_audience`. The legacy nested `user` claim remains supported as a fallback.
- `caronte.session` renews expired or near-expiry user JWTs. JSON/API consumers must replace their local bearer token when a protected response includes `X-User-Token`.
- In OIDC mode, the SDK uses the session-stored OIDC refresh token to renew the ID token before redirecting users back to login.
- Logout routes in the SDK may be called with `GET` or `POST`, but the SDK always calls the Caronte server logout endpoint with `POST`.
- App-to-app credentials use `X-Application-Token` and are checked by `caronte.application`.
- Application-group credentials use `base64(group_id:application_group_secret)`.
- `ApplicationTokens` authenticate external applications consuming this app's API and are checked by `caronte.app-token` plus `caronte.app-permissions:*`.

See [Deployment Instructions](doc/deployment-instructions.md) for the full setup guide.

---

## Documentation Index

- [Deployment Instructions](doc/deployment-instructions.md)
- [API Documentation](doc/api-documentation.md)
- [Routes Documentation](doc/routes-documentation.md)
- [Artisan Commands](doc/artisan-commands.md)
- [Tests Documentation](doc/tests-documentation.md)
- [Architecture Diagrams](doc/architecture-diagrams.md)
- [Monitoring](doc/monitoring.md)
- [Business Logic & Core Processes](doc/business-logic-and-core-processes.md)
- [Open Questions & Assumptions](doc/open-questions-and-assumptions.md)
