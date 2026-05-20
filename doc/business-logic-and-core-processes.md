# Business Logic & Core Processes

## Authentication Flow

1. User submits credentials through the package auth routes.
2. `AuthApi::login()` sends credentials to Caronte with `X-Application-Token`.
3. Caronte returns a user JWT.
4. The package stores the JWT in session for web requests or accepts it as bearer token for JSON/API requests.
5. `caronte.session` validates the token on protected routes.

User tokens can be app-scoped or group-scoped. Group-scoped tokens are validated with `CARONTE_APPLICATION_GROUP_SECRET` and must include the configured `CARONTE_APPLICATION_GROUP_ID`.

The SDK reads phase-2 top-level JWT claims first: `sub`, `aud`, `jti`, `tenant_id`, `roles`, `metadata`, `app_id`, and `token_audience`. The legacy nested `user` claim remains supported as a fallback.

Logout is server-backed. The SDK web route accepts `GET` and `POST`, clears the local session, and calls the Caronte server with `POST /api/auth/logout` or `POST /api/auth/logoutAll`.

## Roles

Roles are user-facing authorization values.

1. Define roles in `config/caronte.php`.
2. Run `php artisan caronte:roles:sync`.
3. Protect routes with `caronte.roles:<role>`.

`root` always satisfies role checks.

## Protected API Scopes

Protected API scopes are not user roles. They describe operations an external client may perform against this host application's API.

1. Define scopes in `config/caronte.php` under `protected_api.scopes`.
2. Run `php artisan caronte:protected-api:scopes:sync`.
3. Caronte server/admin issues a Protected API Access Token for a target app, tenant, and approved scope list.
4. This application protects API routes with `caronte.protected-api-token` and `caronte.protected-api-scopes:<scope>`.

Example:

```php
'protected_api' => [
    'scopes' => [
        'invoices.read' => 'Read invoices',
        'invoices.write' => 'Write invoices',
    ],
],
```

```php
Route::middleware([
    'caronte.protected-api-token',
    'caronte.protected-api-scopes:invoices.read',
])->get('/api/invoices', InvoiceController::class);
```

## Protected API Access Tokens

Protected API Access Tokens are JWT credentials issued by Caronte server/admin for a tenant, target host app, and approved scope list. External clients receive these tokens from Caronte and call this host app with `Authorization: Bearer <protected-api-access-token>`.

The host SDK validates Protected API Access Tokens. It does not issue production tokens for third-party clients.

Validation rules:

- `token_audience` must be `protected_api_access`.
- `app_id` must match this app.
- `aud` must be this app id.
- Signature is verified with `CARONTE_APP_SECRET`.
- `tenant_id` must be present.
- `scopes` must be an array.
- `exp`, `nbf`, and `iat` must be valid.

After `caronte.protected-api-token` passes, `CaronteProtectedApiAccessContext` is available from the container.

Deprecated compatibility names such as `CaronteApplicationAccess*`, `permissions`, `caronte.app-token`, and `caronte.app-permissions` remain only for this migration window and must be removed in the next major version.

## App-To-App Credentials

`caronte.application` validates `X-Application-Token` for service-to-service calls.

- Individual app token: short-lived JWT signed with `CARONTE_APP_SECRET`.
- Group token: short-lived JWT in `X-Group-Token` signed with `CARONTE_APPLICATION_GROUP_SECRET`.

Grouped app-to-app calls send both `X-Application-Token` and `X-Group-Token`. The group JWT identifies group membership and source app traceability; it does not grant Protected API scopes by itself.

## Local User Synchronization

When `caronte.update_local_user=true`, the package updates the local `CaronteUser` cache from validated user JWTs. The host app should still treat Caronte as the source of truth for user identity and role assignments.

## Tenant Resolution

`CaronteTenantResolver` implements Bee Hive's `TenantResolverInterface`. It resolves the tenant from the currently authenticated Caronte user by calling `Caronte::getTenantId()`.

The resolver depends on a valid user JWT in the current request/session. If no user token is available, or if the token has no tenant claim, tenant resolution fails with the same auth/tenant exception path used by `Caronte::getTenantId()`.

In `single` tenancy mode (`caronte.tenancy.mode=single`), the configured `caronte.tenancy.tenant_id` is mandatory and enforced in:

- `AuthController` login path
- `ValidateUserToken` middleware
- `ResolveApplicationContext` middleware

Any mismatch returns `403` and clears invalid user context when applicable.

Local `CaronteUser` rows use `id_tenant` as the Bee Hive tenant key. During local user sync, the SDK temporarily binds `TenantContext` to the token tenant so `BelongsToTenant` writes and reads the correct local tenant cache.

## Provisioning

`ProvisioningApi::provisionTenant()` wraps `POST /api/provisioning/tenants`. Use it only from trusted server-side code configured with an application that has the Caronte platform permission `tenants.provision`.

## Management UI

The package's management UI remains app-local. The global Caronte administration console lives in `mx.ometra.caronte-admin` and manages tenants, applications, groups, application tokens, and global admin workflows.

The app-local management UI supports Blade by default and Inertia when `caronte.management.use_inertia=true`. The Inertia components are published with `php artisan vendor:publish --tag=caronte:inertia`; host apps are responsible for compiling those assets in their own frontend pipeline.

## Local Helper APIs

`CaronteUserHelper` is a read-only helper for the local user cache:

- `getUserName($uriUser)` returns the cached user's name or `User not found`.
- `getUserEmail($uriUser)` returns the cached user's email or `User not found`.
- `getUserMetadata($uriUser, $key)` returns the cached metadata value or `null`.

The helper reads the local `CaronteUser` and `CaronteUserMetadata` models, so Bee Hive tenant context applies. Use it only after the request has an authenticated tenant context or after explicitly binding `TenantContext`.
