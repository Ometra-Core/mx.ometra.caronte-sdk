# API Documentation

This package does not define routes/api.php endpoints. It exposes HTTP endpoints through routes/web.php and returns JSON when clients send JSON expectations.

All package responses follow the CaronteResponse envelope shape:

- status: integer
- message: string
- data: mixed or null
- errors: array (for failures)

## 1. Authentication Endpoints

Base prefix is dynamic:

- auth_prefix = trim(config(caronte.routes_prefix), /)
- login_path = path part of config(caronte.login_url)

With default config, login resolves to /login.

### 1.1 GET /{login_path}

- Route name: caronte.login.form
- Controller: Ometra\Caronte\Http\Controllers\AuthController@loginForm
- Purpose: render login or two-factor login view (or redirect to OIDC login when auth_mode=oidc)
- Auth: public

### 1.2 POST /{login_path}

- Route name: caronte.login
- Controller: AuthController@login
- Purpose: password login or 2FA issue flow depending on config(caronte.use_2fa)
- Auth: public
- Request body:
    - email: required email (except pending tenant selection continuation)
    - password: required string for normal login
    - tenant_id: optional string
    - tenant_selection_token: optional string
    - callback_url: optional string (plain URL or base64 encoded)
- Success:
    - 200 with token in data.token (JSON clients)
    - redirect for browser clients
- Failure examples:
    - 409 tenant selection required with data.tenants
    - 401/403 for auth/tenant errors

### 1.3 GET|POST /logout

- Route name: caronte.logout
- Controller: AuthController@logout
- Purpose: logout current user, optional all sessions
- Request:
    - all: optional boolean

### 1.4 OIDC

- GET /oidc/login (caronte.oidc.login): starts OIDC authorization code flow
- GET /oidc/callback (caronte.oidc.callback): validates state, exchanges code, validates ID token, stores token
- POST /oidc/logout (caronte.oidc.logout): clears local token and redirects to issuer logout endpoint

### 1.5 Two-Factor

- POST /two-factor (caronte.twoFactor.request)
    - body: email
- GET /two-factor/{token} (caronte.twoFactor.login)
    - consumes one-time token and returns authenticated session/token

### 1.6 Password Recovery

- GET /password/recover (caronte.password.recover.form)
- POST /password/recover (caronte.password.recover.request)
- GET /password/recover/{token} (caronte.password.recover.validate-token)
- POST /password/recover/{token} (caronte.password.recover.submit)

POST submit body:

- password: required, min 8, confirmed
- password_confirmation: required

## 2. Management Endpoints

Enabled only when config(caronte.management.enabled)=true.
All management endpoints use middleware:

- caronte.session
- caronte.roles:<configured roles from config(caronte.management.access_roles)>

Default management prefix: /caronte/management.

### 2.1 Dashboard and Role Sync

- GET /caronte/management (caronte.management.dashboard)
    - Purpose: management dashboard
- POST /caronte/management/roles/sync (caronte.management.roles.sync)
    - Purpose: sync configured roles to Caronte server

Legacy role mutation routes exist for compatibility and redirect with warning:

- POST /roles/create (caronte.management.roles.create)
- POST /roles/update (caronte.management.roles.update)
- POST /roles/delete (caronte.management.roles.delete)

### 2.2 Users

- GET /users/list (caronte.management.users.list)
    - Query:
        - search: optional
        - usersApp: optional boolean, default true
    - Returns JSON

- POST /users (caronte.management.users.store)
    - Body:
        - name, email, password, password_confirmation required
        - roles optional array of configured role URIs

- GET /users/{uri_user} (caronte.management.users.show)
    - Returns user detail view

- PUT /users/{uri_user} (caronte.management.users.update.direct)
    - Body: name required

- DELETE /users/{uri_user} (caronte.management.users.delete.direct)

Legacy user mutation compatibility routes:

- POST /users/update (caronte.management.users.update)
- POST /users/delete (caronte.management.users.delete)

Roles and metadata:

- GET /users/{uri_user}/roles (caronte.management.users.roles.list)
- PUT /users/{uri_user}/roles (caronte.management.users.roles.sync)
- POST /users/{uri_user}/metadata (caronte.management.users.metadata.store)
- DELETE /users/{uri_user}/metadata (caronte.management.users.metadata.delete)

## 3. Package Middleware APIs for Host Routes

These are not package-owned routes; they are middleware contracts host apps apply.

### 3.1 caronte.application

- Middleware class: Ometra\Caronte\Http\Middleware\ResolveApplicationContext
- Headers:
    - X-Application-Token required
    - X-Group-Token optional when application group is enabled
    - X-Tenant-Id required when tenant_required mode is used
    - X-User-Token optional or required when user_required mode is used

Modes:

- tenant_required
- user_required

### 3.2 caronte.protected-api-token and caronte.protected-api-scopes

- Validates bearer token as protected API access token
- Scope middleware checks required scopes list

Compatibility aliases (deprecated):

- caronte.app-token
- caronte.app-permissions

## 4. Example JSON Response

Success:

{
"status": 200,
"message": "Token generated",
"data": {
"token": "<jwt>"
}
}

Failure:

{
"status": 403,
"message": "User does not have access to this feature.",
"errors": [
"User does not have the required roles: admin"
]
}

## 5. Notes

- There is no package-shipped REST API versioning layer.
- Public routes are web-routed and can still return JSON.
- Unknown downstream schemas from Caronte server are tracked in doc/open-questions-and-assumptions.md.
