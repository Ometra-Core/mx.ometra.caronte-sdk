# Client Handover

This handover is for Laravel host applications that install `ometra/caronte-sdk`.
It defines the target integration contract and the legacy names that remain
temporarily available during the migration.

## Responsibilities

The host application is responsible for:

- Installing and configuring this SDK.
- Protecting web, API, app-to-app, and externally consumed routes with the correct middleware.
- Declaring protected API scopes and synchronising them with Caronte.
- Validating Protected API Access Tokens issued by Caronte server/admin.

The host application is not responsible for issuing Protected API Access Tokens
to third-party clients.

## Required Configuration

Minimum `.env` values:

```env
CARONTE_URL=https://caronte.example.com
CARONTE_APP_CN=billing-service
CARONTE_APP_SECRET=a-secret-at-least-32-characters-long
```

Optional application group values:

```env
CARONTE_APPLICATION_GROUP_ID=core-suite
CARONTE_APPLICATION_GROUP_SECRET=a-group-secret-at-least-32-characters-long
```

The app canonical name (`CARONTE_APP_CN`) identifies this host application in
Caronte. The app secret signs internal Application Auth Tokens and validates
Protected API Access Tokens that target this host app.

## Token Taxonomy

| Token type | Used by | Transport | Middleware | Purpose |
| ---------- | ------- | --------- | ---------- | ------- |
| User Token | Browser/API user | Web session or `Authorization: Bearer <user-jwt>` | `caronte.session` | Authenticates a human user |
| Application Auth Token | Caronte SDK / app-to-app caller | `X-Application-Token` | `caronte.application` | Authenticates the calling app |
| Application Group Auth Token | Caronte SDK / app-to-app caller in a group | `X-Group-Token` plus `X-Application-Token` | `caronte.application` | Authenticates group membership and source app |
| Protected API Access Token | External API consumer | `Authorization: Bearer <protected-api-access-token>` | `caronte.protected-api-token` | Authorizes access to this host app's API scopes |

`X-User-Token` is not the incoming auth header for host API routes. It is used
by the SDK when forwarding the current user token to Caronte/other services, and
as a response header when `caronte.session` refreshes a user token during a JSON
or API request.

## Middleware Requirements

| Middleware | Required input | Must run after | Notes |
| ---------- | -------------- | -------------- | ----- |
| `caronte.session` | Web session token for web routes, or `Authorization: Bearer <user-jwt>` for API routes | None | May return refreshed token in `X-User-Token` |
| `caronte.roles:<role>` | Valid user context from `caronte.session` | `caronte.session` | `root` satisfies all role checks |
| `caronte.application` | `X-Application-Token`; if grouped, also `X-Group-Token` | None | For app-to-app routes only |
| `caronte.application:tenant_required` | Same as `caronte.application`, plus `X-Tenant-Id` unless single tenant config binds it | None | Use when tenant context is mandatory |
| `caronte.protected-api-token` | `Authorization: Bearer <protected-api-access-token>` | None | For external clients consuming this app's API |
| `caronte.protected-api-scopes:<scope>` | Valid protected API context | `caronte.protected-api-token` | Requires all listed scopes |

## Web Routes

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'caronte.session'])
    ->get('/dashboard', DashboardController::class);

Route::middleware(['web', 'caronte.session', 'caronte.roles:admin'])
    ->get('/admin', AdminController::class);
```

For web routes, the SDK reads the user token from the configured session key.

## User-Authenticated API Routes

```php
Route::middleware(['caronte.session'])
    ->get('/api/me', CurrentUserController::class);
```

Client request:

```http
GET /api/me HTTP/1.1
Authorization: Bearer <user-jwt>
Accept: application/json
```

If the token is refreshed, the response includes:

```http
X-User-Token: <fresh-user-jwt>
```

The API client must replace its stored user token with this value.

## App-To-App Routes

Protect internal service routes with `caronte.application`.

```php
Route::middleware(['caronte.application'])
    ->post('/internal/reprice', RepriceController::class);

Route::middleware(['caronte.application:tenant_required'])
    ->post('/internal/tenant-report', TenantReportController::class);
```

The SDK sends:

```http
X-Application-Token: <application-auth-jwt>
```

If the app has group credentials configured, it also sends:

```http
X-Group-Token: <application-group-auth-jwt>
```

Do not use Protected API Access Tokens for app-to-app calls between trusted
Caronte host applications.

## Protected API Scopes

Protected API scopes describe what an external API consumer can do against this
host app's API. They are not user roles and they are not app-to-app credentials.

Target config:

```php
// config/caronte.php
'protected_api' => [
    'scopes' => [
        'invoices.read' => 'Read invoices',
        'invoices.write' => 'Write invoices',
    ],
],
```

Synchronise scopes with Caronte:

```bash
php artisan caronte:protected-api:scopes:sync --dry-run
php artisan caronte:protected-api:scopes:sync
```

Caronte server/admin uses these synced scopes when issuing Protected API Access
Tokens for external clients.

## Protected API Routes

```php
Route::middleware([
    'caronte.protected-api-token',
    'caronte.protected-api-scopes:invoices.read',
])->get('/api/invoices', InvoiceIndexController::class);
```

External client request:

```http
GET /api/invoices HTTP/1.1
Authorization: Bearer <protected-api-access-token>
Accept: application/json
```

The host app validates the token locally and binds a protected API access
context for the current request. It does not call Caronte to introspect every
request.

## Adoption Checklist

- [ ] Configure `CARONTE_URL`, `CARONTE_APP_CN`, and `CARONTE_APP_SECRET`.
- [ ] Configure application group credentials if this app belongs to a group.
- [ ] Protect web routes with `caronte.session`.
- [ ] Protect role-gated routes with `caronte.roles`.
- [ ] Protect internal app-to-app routes with `caronte.application`.
- [ ] Declare `protected_api.scopes`.
- [ ] Run `caronte:protected-api:scopes:sync`.
- [ ] Ask Caronte server/admin to issue Protected API Access Tokens for external clients.
- [ ] Protect external API routes with `caronte.protected-api-token` and `caronte.protected-api-scopes`.

## Deprecated Legacy Names

These names remain available only for compatibility in this version. They must
be removed in the next major version:

- Classes named `CaronteApplicationAccess*`.
- Middleware alias `caronte.app-token`.
- Middleware alias `caronte.app-permissions`.
- Config key `permissions`.
- Command `caronte:permissions:sync`.
- Endpoint, payload, or JWT claim named `permissions`.

Use only the protected API/scopes names in new host applications.
