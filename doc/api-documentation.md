# API Documentation

This package exposes HTTP endpoints through `routes/web.php` and supports JSON or redirect-style responses depending on request expectations. There is no `routes/api.php` file in the package.

## Authentication Endpoints

### GET /{auth_prefix}/{login_path}

- Route name: `caronte.login.form`
- Controller: `Ometra\Caronte\Http\Controllers\AuthController@loginForm`
- Auth: public
- Purpose: render login form (or redirect to OIDC login when `caronte.auth_mode=oidc`)

Response:

- Web: Blade/Inertia page with branding and route metadata
- JSON callers are not a target for this route

### POST /{auth_prefix}/{login_path}

- Route name: `caronte.login`
- Controller: `Ometra\Caronte\Http\Controllers\AuthController@login`
- Auth: public
- Purpose: user/password login, 2FA request path, and tenant-selection continuation

Request body:

- `email` required unless continuing a pending tenant-selection step
- `password` required unless continuing a pending tenant-selection step
- `tenant_id` optional (forced in single-tenant mode)

Status codes:

- `200` success
- `401/403/422` validation/auth/authorization failures
- `409` tenant selection required (JSON callers)

Example JSON success:

```json
{
    "status": 200,
    "message": "Login successful",
    "data": {
        "token": "<jwt>"
    }
}
```

### GET|POST /{auth_prefix}/logout

- Route name: `caronte.logout`
- Controller: `Ometra\Caronte\Http\Controllers\AuthController@logout`
- Auth: current user session/token
- Purpose: clear local token and revoke server session

Request:

- Optional query/body `all=true` to call Caronte `logoutAll`

Status codes:

- `200` success (JSON)
- `302` redirect (web)

### POST /{auth_prefix}/two-factor

- Route name: `caronte.twoFactor.request`
- Controller: `Ometra\Caronte\Http\Controllers\AuthController@twoFactorTokenRequest`
- Auth: public

Request body:

- `email` required

### GET /{auth_prefix}/two-factor/{token}

- Route name: `caronte.twoFactor.login`
- Controller: `Ometra\Caronte\Http\Controllers\AuthController@twoFactorTokenLogin`
- Auth: public

Path params:

- `token` required

### GET|POST /{auth_prefix}/password/recover

- Route names: `caronte.password.recover.form`, `caronte.password.recover.request`
- Controller: `Ometra\Caronte\Http\Controllers\AuthController`
- Auth: public

Request body for POST:

- `email` required

### GET|POST /{auth_prefix}/password/recover/{token}

- Route names: `caronte.password.recover.validate-token`, `caronte.password.recover.submit`
- Controller: `Ometra\Caronte\Http\Controllers\AuthController`
- Auth: public

POST request body:

- `password` required, min 8
- `password_confirmation` required and must match

### GET /{auth_prefix}/oidc/login

- Route name: `caronte.oidc.login`
- Controller: `Ometra\Caronte\Http\Controllers\OidcAuthController@redirect`
- Auth: public
- Purpose: start OIDC authorization-code flow with PKCE

### GET /{auth_prefix}/oidc/callback

- Route name: `caronte.oidc.callback`
- Controller: `Ometra\Caronte\Http\Controllers\OidcAuthController@callback`
- Auth: public
- Purpose: consume authorization code, validate `id_token`, persist token

### POST /{auth_prefix}/oidc/logout

- Route name: `caronte.oidc.logout`
- Controller: `Ometra\Caronte\Http\Controllers\OidcAuthController@logout`
- Auth: current user session/token

## Management Endpoints

All management endpoints are conditionally enabled by `caronte.management.enabled` and protected by:

- `caronte.session`
- `caronte.roles:{configured access roles}`

### GET /{management_prefix}

- Route name: `caronte.management.dashboard`
- Controller: `Ometra\Caronte\Http\Controllers\ManagementController@dashboard`
- Purpose: render user/role management dashboard

### POST /{management_prefix}/roles/sync

- Route name: `caronte.management.roles.sync`
- Controller: `Ometra\Caronte\Http\Controllers\RoleController@sync`
- Purpose: synchronize configured roles to Caronte server

### GET /{management_prefix}/users/list

- Route name: `caronte.management.users.list`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@list`
- Purpose: list users as JSON

Query params:

- `search` optional
- `usersApp` optional boolean (defaults true)

### POST /{management_prefix}/users

- Route name: `caronte.management.users.store`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@store`
- Purpose: create user and sync selected roles

Request body:

- `name` required
- `email` required
- `password` required
- `password_confirmation` required
- `roles[]` optional, each must match a configured role URI

### GET /{management_prefix}/users/{uri_user}

- Route name: `caronte.management.users.show`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@show`

### PUT /{management_prefix}/users/{uri_user}

- Route name: `caronte.management.users.update.direct`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@update`

Request body:

- `name` required

### POST /{management_prefix}/users/update

- Route name: `caronte.management.users.update`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@updateLegacy`

Request body:

- `uri_user` required
- `name` required

### DELETE /{management_prefix}/users/{uri_user}

- Route name: `caronte.management.users.delete.direct`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@delete`

### POST /{management_prefix}/users/delete

- Route name: `caronte.management.users.delete`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@deleteLegacy`

Request body:

- `uri_user` required

### GET /{management_prefix}/users/{uri_user}/roles

- Route name: `caronte.management.users.roles.list`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@listRoles`
- Purpose: JSON list of assigned roles

### PUT /{management_prefix}/users/{uri_user}/roles

- Route name: `caronte.management.users.roles.sync`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@syncRoles`

Request body:

- `roles[]` optional, each must match configured role URI

### POST /{management_prefix}/users/{uri_user}/metadata

- Route name: `caronte.management.users.metadata.store`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@storeMetadata`

Request body:

- `key` required
- `value` optional

### DELETE /{management_prefix}/users/{uri_user}/metadata

- Route name: `caronte.management.users.metadata.delete`
- Controller: `Ometra\Caronte\Http\Controllers\UserController@deleteMetadata`

Request body:

- `key` required

## App-to-App and External Consumer Security Middleware

The package does not register concrete API URIs for these middleware aliases, but host applications use them to protect their own API routes:

- `caronte.application[:tenant_required]` validates short-lived `X-Application-Token` JWTs; grouped calls also validate `X-Group-Token`
- `caronte.protected-api-token` validates bearer Protected API Access Tokens issued by Caronte server/admin
- `caronte.protected-api-scopes:<scope>` checks protected API scopes
- `caronte.app-token` and `caronte.app-permissions:<permission>` are deprecated compatibility aliases and must be removed in the next major version

Example host route:

```php
Route::middleware([
        'caronte.protected-api-token',
        'caronte.protected-api-scopes:invoices.read',
])->get('/api/invoices', InvoiceController::class);
```

External consumers call this route with:

```http
Authorization: Bearer <protected-api-access-token>
Accept: application/json
```

Caronte server/admin is responsible for issuing Protected API Access Tokens.
The host SDK validates them locally and does not expose a public production API
for third-party token generation.
