# Routes Documentation

Package routes are loaded by Ometra\Caronte\Providers\CaronteServiceProvider from both `routes/web.php` and `routes/api.php`.

## 1. Route Registration Model

- Service provider: Ometra\Caronte\Providers\CaronteServiceProvider
- Loaders:
    - `loadRoutesFrom(routes/web.php)` inside a `web` middleware group
    - `loadRoutesFrom(routes/api.php)` inside an `api` middleware group
- Route files:
    - `routes/web.php`
    - `routes/api.php`
- Route-group prefixes:
    - web auth routes use `config(caronte.routes.prefix)` with the `caronte.` name prefix
    - API client auth routes use the fixed `/api/caronte/auth` prefix with the `caronte.api.auth.` name prefix

Management routes are conditionally registered only when config(caronte.management.enabled) is true.

## 2. Browser Authentication Routes

| Method   | URI Pattern               | Route Name                              | Controller Method                             | Middleware |
| -------- | ------------------------- | --------------------------------------- | --------------------------------------------- | ---------- |
| GET      | /{login_path}             | caronte.login.form                      | AuthController@loginForm                      | web        |
| POST     | /{login_path}             | caronte.login                           | AuthController@login                          | web        |
| GET,POST | /logout                   | caronte.logout                          | AuthController@logout                         | web        |
| GET      | /oidc/login               | caronte.oidc.login                      | OidcAuthController@redirect                   | web        |
| GET      | /oidc/callback            | caronte.oidc.callback                   | OidcAuthController@callback                   | web        |
| POST     | /oidc/logout              | caronte.oidc.logout                     | OidcAuthController@logout                     | web        |
| POST     | /two-factor               | caronte.twoFactor.request               | AuthController@twoFactorTokenRequest          | web        |
| GET      | /two-factor/{token}       | caronte.twoFactor.login                 | AuthController@twoFactorTokenLogin            | web        |
| GET      | /password/recover         | caronte.password.recover.form           | AuthController@passwordRecoverRequestForm     | web        |
| POST     | /password/recover         | caronte.password.recover.request        | AuthController@passwordRecoverRequest         | web        |
| GET      | /password/recover/{token} | caronte.password.recover.validate-token | AuthController@passwordRecoverTokenValidation | web        |
| POST     | /password/recover/{token} | caronte.password.recover.submit         | AuthController@passwordRecover                | web        |

Notes:

- login_path is derived from config(caronte.routes.login_url).
- auth prefix is config(caronte.routes.prefix).

## 3. JSON Client Authentication Routes

Base prefix: `/api/caronte/auth`.

Common middleware:

- `api`
- `Equidna\Toolkit\Http\Middleware\ForceJsonResponse`
- `caronte.session` on authenticated routes only

| Method | URI Pattern              | Route Name              | Controller Method        | Middleware                              |
| ------ | ------------------------ | ----------------------- | ------------------------ | --------------------------------------- |
| POST   | /api/caronte/auth/login  | caronte.api.auth.login  | ApiAuthController@login  | api, ForceJsonResponse                  |
| GET    | /api/caronte/auth/me     | caronte.api.auth.me     | ApiAuthController@me     | api, ForceJsonResponse, caronte.session |
| POST   | /api/caronte/auth/logout | caronte.api.auth.logout | ApiAuthController@logout | api, ForceJsonResponse, caronte.session |

Notes:

- These endpoints are intended for mobile, CLI, Python, and other non-browser clients.
- They always return JSON, including validation failures and tenant-selection conflicts.
- When `caronte.session` refreshes a bearer token, the response returns the refreshed credential in `X-User-Token`.

## 4. Management Routes

Base prefix: config(caronte.management.route_prefix), default caronte/management.

Common middleware for all management routes:

- caronte.session
- caronte.roles:<roles from config(caronte.management.access_roles)>

| Method | URI Pattern                                   | Route Name                               | Controller Method                        |
| ------ | --------------------------------------------- | ---------------------------------------- | ---------------------------------------- |
| GET    | /caronte/management                           | caronte.management.dashboard             | ManagementController@dashboard           |
| POST   | /caronte/management/roles/sync                | caronte.management.roles.sync            | RoleController@sync                      |
| POST   | /caronte/management/roles/create              | caronte.management.roles.create          | RoleController@unsupportedLegacyMutation |
| POST   | /caronte/management/roles/update              | caronte.management.roles.update          | RoleController@unsupportedLegacyMutation |
| POST   | /caronte/management/roles/delete              | caronte.management.roles.delete          | RoleController@unsupportedLegacyMutation |
| GET    | /caronte/management/users/list                | caronte.management.users.list            | UserController@list                      |
| POST   | /caronte/management/users                     | caronte.management.users.store           | UserController@store                     |
| GET    | /caronte/management/users/{uri_user}          | caronte.management.users.show            | UserController@show                      |
| POST   | /caronte/management/users/update              | caronte.management.users.update          | UserController@updateLegacy              |
| PUT    | /caronte/management/users/{uri_user}          | caronte.management.users.update.direct   | UserController@update                    |
| POST   | /caronte/management/users/delete              | caronte.management.users.delete          | UserController@deleteLegacy              |
| DELETE | /caronte/management/users/{uri_user}          | caronte.management.users.delete.direct   | UserController@delete                    |
| GET    | /caronte/management/users/{uri_user}/roles    | caronte.management.users.roles.list      | UserController@listRoles                 |
| PUT    | /caronte/management/users/{uri_user}/roles    | caronte.management.users.roles.sync      | UserController@syncRoles                 |
| POST   | /caronte/management/users/{uri_user}/metadata | caronte.management.users.metadata.store  | UserController@storeMetadata             |
| DELETE | /caronte/management/users/{uri_user}/metadata | caronte.management.users.metadata.delete | UserController@deleteMetadata            |

## 5. Middleware Aliases Exposed by the Package

Registered in CaronteServiceProvider:

- caronte.session -> ValidateUserToken
- caronte.roles -> ValidateUserRoles
- caronte.application -> ResolveApplicationContext
- caronte.protected-api-token -> ValidateProtectedApiAccessToken
- caronte.protected-api-scopes -> ValidateProtectedApiScopes

## 6. Host Application Expectations

As this is a package:

- Route prefixes and login path are host-configurable.
- Host apps should use package middleware on their own API routes for app-to-app and protected API authorization.
- Package-owned browser auth and management routes are mounted through `routes/web.php`.
- Package-owned JSON auth endpoints are mounted through `routes/api.php` under `/api/caronte/auth`.
