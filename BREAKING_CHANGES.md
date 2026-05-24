# Breaking Changes

## v4.4.0

### Breaking status

No breaking changes were introduced in this release.

### Migration recommendations

1. If host integrations validate role names client-side, allow dots (`.`) so validation matches SDK behavior.
2. For middleware-focused operations, use the new index at `doc/middleware.md` and the per-alias pages under `doc/middleware/`.

### Notes

- `ResolveApplicationContext` internals were refactored into dedicated resolver classes without changing public middleware aliases.

## v4.3.0

### Breaking status

No breaking changes were introduced in this release.

### Migration recommendations

1. If delegated user context is required on app-to-app routes, include `user_required` in the application middleware mode list and pass `X-User-Token`.
2. For routes requiring both delegated user context and tenant context, use `caronte.application:tenant_required,user_required` and provide `X-Tenant-Id` when the forwarded token does not include `tenant_id`.

### Notes

- New forwarded-user behavior is backward-compatible for existing `caronte.application` routes that do not opt into `user_required`.

## v4.2.0

### Breaking status

No breaking changes were introduced in this release.

### Migration recommendations

1. Prefer configuring protected API authorization under `protected_api.scopes` instead of legacy permission naming.
2. Move route protection to protected API middleware aliases where applicable:
    - `caronte.protected-api-token`
    - `caronte.protected-api-scopes:*`
3. Replace legacy permission sync usage with:

```bash
php artisan caronte:protected-api:scopes:sync
```

### Notes

- Permission-oriented aliases and API compatibility shims are still available for migration windows.
- Compatibility layers are expected to be removed in the next major release, so host apps should migrate during 4.x.

## v4.1.0

### What Changed

1. **Tenant key standardized to `tenant_id`** — package internals now treat `tenant_id` as the canonical tenant field for users, metadata, helpers, and command output.
2. **Composite primary keys are now required for tenant-safe identity**:
    - `Users`: `['uri_user', 'tenant_id']`
    - `UsersMetadata`: `['uri_user', 'tenant_id', 'scope', 'key']`
3. **Model identity semantics changed** — `CaronteUser` and `CaronteUserMetadata` use `HasCompositePrimaryKey`; host customizations relying on single-key assumptions must be updated.

### Why

Earlier behavior mixed legacy and modern tenant field naming (`id_tenant` vs `tenant_id`) and allowed single-column assumptions that are unsafe in multi-tenant contexts. This release makes tenant scoping explicit and consistent across migrations, models, helper queries, and token-derived user sync.

### Migration

1. Run migrations in the host application:

```bash
php artisan migrate
```

2. Update custom SQL/Eloquent code that filters on `id_tenant` to use `tenant_id`.

3. Update custom logic that assumes `uri_user` is globally unique. Use composite identity:

```php
// Before
$user = CaronteUser::query()->where('uri_user', $uriUser)->first();

// After
$user = CaronteUser::query()
    ->where('uri_user', $uriUser)
    ->where('tenant_id', $tenantId)
    ->first();
```

4. If your host application manually upserts metadata, include `tenant_id` in both the match and payload arrays:

```php
// Before
CaronteUserMetadata::updateOrCreate(
    ['uri_user' => $uriUser, 'scope' => $scope, 'key' => $key],
    ['value' => $value]
);

// After
CaronteUserMetadata::updateOrCreate(
    ['uri_user' => $uriUser, 'tenant_id' => $tenantId, 'scope' => $scope, 'key' => $key],
    ['value' => $value]
);
```

5. Verify any downstream integrations that read SDK command output now consume `tenant_id` as the tenant column key.

### Notes

- Migrations include a compatibility backfill path for existing rows where `tenant_id` is missing.
- No additional environment variables are required for this release.

## v3.6.0

### Breaking status

No breaking changes were introduced in this release.

### Migration

No migration steps are required.

## v3.5.0

### Breaking status

No breaking changes were introduced in this release.

### Migration

No migration steps are required.

---

## v3.2.0

### What Changed

1. **`Support/RouteMode` class removed** — `Ometra\Caronte\Support\RouteMode` no longer exists. The class was an internal helper that detected whether a request expected a JSON or web (HTML) response. Its functionality has been inlined directly into `CaronteResponse`, `AuthController`, and middleware using standard Laravel `Request` methods.

### Why

`RouteMode` was marked internally as a candidate for removal (see the `// NOTE FIND ANOTHER WAY...` comment in the class). It was a `static`-only helper with no test coverage and introduced tight coupling between unrelated classes. Inlining the detection eliminates the coupling and makes behaviour testable per call-site.

### Migration: `RouteMode` Removal

If your host application directly references `Ometra\Caronte\Support\RouteMode`, replace it with equivalent `Request` calls:

**Before (≤ v3.1.0)**

```php
use Ometra\Caronte\Support\RouteMode;

if (RouteMode::wantsJson()) {
    return response()->json($data);
}

if (RouteMode::isWeb()) {
    return redirect()->back();
}
```

**After (v3.2.0+)**

```php
// Inject or resolve the current request
$request = request();

if ($request->expectsJson() || $request->wantsJson() || $request->is('api/*')) {
    return response()->json($data);
}

// "isWeb" is simply the inverse of wantsJson
return redirect()->back();
```

> If you were not importing `RouteMode` directly in your host application, no changes are required.

---

## v3.1.0

### What Changed

1. **`equidna/bee-hive` raised to `^3.0`** — BeeHive 2.x is no longer supported. Host applications that constrain BeeHive at `^2.x` must upgrade BeeHive before upgrading to Caronte SDK `^3.1`.

### Why

BeeHive 3.0 introduces improved tenant context binding APIs used by Caronte SDK's `syncUser()` flow and the `BindsTenantContext` console concern. Maintaining backwards-compatibility with BeeHive 2.x would have required version-conditional shims across multiple call sites.

### Migration: BeeHive Upgrade

1. Update your `composer.json` to require `equidna/bee-hive: ^3.0`.
2. Run `composer update equidna/bee-hive`.
3. Review the BeeHive 3.0 changelog for any additional breaking changes in that package.

> No Caronte SDK class names, method signatures, middleware aliases, or config keys changed in this release.

---

## v3.0.0

### What Changed

1. **`CARONTE_APP_ID` → `CARONTE_APP_CN`** — the environment variable identifying the application was renamed to match the canonical `app_cn` nomenclature.
2. **Config keys normalised to `lower_snake_case`** — `app_id` → `app_cn`; references to `config('caronte.APP_ID')` or `config('caronte.ISSUER_ID')` must be updated.
3. **Core class renames** — `CaronteToken` → `CaronteUserToken`; `ServiceClient` → `CaronteServiceClient`; `ApplicationToken` → `CaronteApplicationToken` (moved to `Support/` namespace).
4. **`CaronteHttpClient` namespace change** — moved from `Ometra\Caronte\Api` to `Ometra\Caronte\Support`.
5. **Middleware renames** — `ValidateSession` → `ValidateUserToken`; `ValidateRoles` → `ValidateUserRoles`.
6. **Two middleware replaced by one** — `ResolveApplicationToken` and `ResolveTenantContext` are gone; their combined responsibility is now handled by `ResolveApplicationContext`.
7. **`CaronteRequest` removed** — the legacy HTTP wrapper is gone; use `CaronteHttpClient` (`Ometra\Caronte\Support`) directly.
8. **`CaronteRoleManager` removed** — use `RoleApi` directly for role synchronisation.
9. **`GuardsManagement` concern removed** — Artisan commands requiring a management guard now use `BindsTenantContext`.
10. **`RequestContext` and `TenantContextResolver` removed** — absorbed into `ResolveApplicationContext`.
11. **`BaseApiClient` removed** — extend `CaronteApiClient` instead.
12. **`ManagementCaronte` command class removed** — the `caronte:admin` interactive TUI is retained under a new direct implementation.

### Why

- **Naming consistency**: `CARONTE_APP_ID` conflicted with the canonical identifier (`Common Name`) used by the Caronte server. `CARONTE_APP_CN` (Common Name) aligns client and server terminology.
- **API surface pruning**: `CaronteRequest`, `CaronteRoleManager`, and `BaseApiClient` were fragile thin wrappers that leaked implementation details. Removing them collapses the call chain, reduces confusion, and makes the real entry-points (`CaronteHttpClient`, `RoleApi`) first-class.
- **Middleware consolidation**: `ResolveApplicationToken` and `ResolveTenantContext` always ran together and shared state; merging them into `ResolveApplicationContext` eliminates ordering bugs and duplication.
- **`lower_snake_case` config**: Aligns with Laravel conventions and removes an entire class of subtle bugs from case-sensitive config lookups.

---

### Migration: Environment Variable Rename

**Before (≤ v2.1.1)**

```dotenv
CARONTE_APP_ID=my-app
```

**After (v3.0.0+)**

```dotenv
CARONTE_APP_CN=my-app
```

Update `.env`, `.env.example`, CI/CD secrets, and any deployment pipeline that sets `CARONTE_APP_ID`.

---

### Migration: Config Key Access

No action needed if you access configuration **only** through the published `config/caronte.php` file (the keys are already updated). If your code reads config values directly:

**Before**

```php
config('caronte.APP_ID')
config('caronte.ISSUER_ID')
```

**After**

```php
config('caronte.app_cn')
config('caronte.issuer_id')
```

---

### Migration: Class Renames

| Before (≤ v2.1.1)                         | After (v3.0.0+)                                  |
| ----------------------------------------- | ------------------------------------------------ |
| `Ometra\Caronte\CaronteToken`             | `Ometra\Caronte\CaronteUserToken`                |
| `Ometra\Caronte\ServiceClient`            | `Ometra\Caronte\CaronteServiceClient`            |
| `Ometra\Caronte\Support\ApplicationToken` | `Ometra\Caronte\Support\CaronteApplicationToken` |
| `Ometra\Caronte\Api\CaronteHttpClient`    | `Ometra\Caronte\Support\CaronteHttpClient`       |

Update all `use` statements and type hints accordingly.

---

### Migration: Middleware Renames

| Before (≤ v2.1.1)                             | After (v3.0.0+)                                      |
| --------------------------------------------- | ---------------------------------------------------- |
| `Caronte.ValidateSession` / `ValidateSession` | `Caronte.ValidateUserToken` / `ValidateUserToken`    |
| `Caronte.ValidateRoles` / `ValidateRoles`     | `Caronte.ValidateUserRoles` / `ValidateUserRoles`    |
| `Caronte.ResolveApplicationToken`             | `Caronte.ResolveApplicationContext`                  |
| `Caronte.ResolveTenantContext`                | _(removed; merged into `ResolveApplicationContext`)_ |

In your `bootstrap/app.php` or `Kernel.php`:

```php
// Before
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'caronte.auth'    => \Ometra\Caronte\Http\Middleware\ValidateSession::class,
        'caronte.roles'   => \Ometra\Caronte\Http\Middleware\ValidateRoles::class,
        'caronte.app'     => \Ometra\Caronte\Http\Middleware\ResolveApplicationToken::class,
        'caronte.tenant'  => \Ometra\Caronte\Http\Middleware\ResolveTenantContext::class,
    ]);
})

// After
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'caronte.auth'  => \Ometra\Caronte\Http\Middleware\ValidateUserToken::class,
        'caronte.roles' => \Ometra\Caronte\Http\Middleware\ValidateUserRoles::class,
        'caronte.app'   => \Ometra\Caronte\Http\Middleware\ResolveApplicationContext::class,
    ]);
})
```

---

### Migration: Removed Classes

#### `CaronteRequest`

**Before**

```php
use Ometra\Caronte\CaronteRequest;

$response = CaronteRequest::post('/auth/login', $payload);
```

**After** — use `CaronteHttpClient` from `Support`:

```php
use Ometra\Caronte\Support\CaronteHttpClient;

$client   = new CaronteHttpClient();
$response = $client->post('/auth/login', $payload);
```

#### `CaronteRoleManager`

**Before**

```php
use Ometra\Caronte\CaronteRoleManager;

CaronteRoleManager::syncConfiguredRoles();
```

**After** — use `RoleApi` directly:

```php
use Ometra\Caronte\Api\RoleApi;

(new RoleApi())->syncConfiguredRoles();
```

#### `BaseApiClient`

**Before**

```php
use Ometra\Caronte\Api\BaseApiClient;

class MyApi extends BaseApiClient { ... }
```

**After**

```php
use Ometra\Caronte\Api\CaronteApiClient;

class MyApi extends CaronteApiClient { ... }
```

---

## v2.1.0

### What Changed

1. **JWT issuer validation now defaults to `true`** (`CARONTE_ENFORCE_ISSUER`).
2. **Legacy Artisan commands removed** — the monolithic and individual role/user command classes were replaced by a new, focused command suite.

### Why

- Issuer validation was silently disabled by default, creating a window for token forgery from any HS256-compatible source sharing the same secret. Enforcing it by default closes this gap.
- The legacy command set (`ManagementRoles`, `ManagementUsers`, individual `CreateRole`/`DeleteRole`/etc.) was split into composable, focused commands aligned with the domain model.

---

### Migration: Issuer Validation Default

**Before (≤ v2.0.0)**

Issuer validation was **disabled** by default. The JWT `iss` claim was not checked unless you explicitly set:

```dotenv
CARONTE_ENFORCE_ISSUER=true
CARONTE_ISSUER_ID=caronte
```

**After (v2.1.0+)**

Issuer validation is **enabled** by default with `CARONTE_ISSUER_ID=caronte`.

If your Caronte server issues tokens with a different issuer identifier, set:

```dotenv
CARONTE_ISSUER_ID=your-custom-issuer
```

If you need to disable issuer validation entirely (not recommended):

```dotenv
CARONTE_ENFORCE_ISSUER=false
```

**Affected surface:** `src/CaronteToken.php`, `config/caronte.php`.

---

### Migration: Artisan Commands

The following commands **no longer exist** as of v2.1.0:

| Removed command                    | Replacement                                           |
| ---------------------------------- | ----------------------------------------------------- |
| `caronte-client:management` (TUI)  | `caronte:admin`                                       |
| Monolithic role management         | `caronte:roles:sync [--dry-run]`                      |
| Monolithic user management         | `caronte:users:list`, `caronte:users:create`, etc.    |
| `AttachRoles` / `DeleteRolesUser`  | `caronte:users:roles:sync`                            |
| `CreateRole` / `UpdateRole` / etc. | `caronte:roles:sync` (single sync command)            |
| `ShowRolesByUser`                  | `caronte:users:list` with `--search` + inspect output |

**Update any scripts, CI pipelines, or documentation** that reference the old command signatures.

**Affected surface:** `src/Console/Commands/`.

---

## v2.0.0

### What Changed

- `Caronte::getTenant()` was renamed to `Caronte::getTenantId()`.
- Tenant access now returns a string (`id_tenant`) instead of a tenant object payload.
- Default fallback payload for missing tenant data was removed.
- `equidna/bee-hive` is now a required dependency.
- Package baseline is now PHP `^8.2` and Laravel `^12.0`.

### Why

The new API makes tenant usage explicit and stable for multi-tenant checks:

- Consumers usually need only `id_tenant`.
- Returning a strict `string` reduces ambiguity.
- Missing tenant identifiers now fail fast with `TenantMissingException`.

### Migration Steps

1. Replace method calls:

```php
// Before (v1.6.x)
$tenant = Caronte::getTenant();
$tenantId = (string) $tenant->id_tenant;
```

```php
// After (v2.0.0)
$tenantId = Caronte::getTenantId();
```

2. Remove fallback assumptions:

```php
// Before
$tenant = Caronte::getTenant();
if ($tenant->id_tenant === 0) {
    // no tenant
}
```

```php
// After
try {
    $tenantId = Caronte::getTenantId();
} catch (\Ometra\Caronte\Exceptions\TenantMissingException $e) {
    // handle missing tenant
}
```

3. Update custom wrappers and type hints from `stdClass` to `string` where applicable.

4. Ensure BeeHive configuration is present in the host application and select the desired resolver.

```bash
php artisan vendor:publish --tag=bee-hive:config
```

### Affected Surface

- `src/Caronte.php`
- `src/Facades/Caronte.php`
- `README.md`

### Related Docs

- `CHANGELOG.md` for complete project history and release grouping.
- `RELEASE_NOTES.md` for high-level release communication.
