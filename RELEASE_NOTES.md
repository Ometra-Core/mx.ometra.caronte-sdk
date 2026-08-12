# Release v8.3.1

> **Release date:** 2026-08-12
> **Type:** Patch - Delegated login callback fallback behavior.

## Summary

Refines delegated login redirects produced by `BlockLoginRouteWhenDisabled` so callback behavior is explicit and fail-safe.

## Highlights

- Callback fallback order is now `caronte.routes.success_url`, then `APP_URL`.
- If no fallback URL is available, redirect to delegated login no longer appends `callback_url`.
- Added feature-test coverage for both callback-present and callback-absent scenarios.

## Breaking Changes

None.

---

# Release v8.3.0

> **Release date:** 2026-08-11
> **Type:** Patch - Login form route protection for delegated authentication.

## Summary

Introduces the `caronte.block-login-route-when-disabled` middleware to protect login form routes when delegated to an external service.

## Highlights

- New middleware blocks direct access to built-in login form when `CARONTE_LOGIN_VIEW_ENABLED=false`
- Redirects to external login URL configured in `CARONTE_LOGIN_URL`
- Automatically applied to auth route group

## Configuration

```dotenv
CARONTE_AUTH_ROUTES_ENABLED=false
CARONTE_LOGIN_URL=https://identity.example.com/login
CARONTE_LOGIN_VIEW_ENABLED=false
```

## Breaking Changes

None.

---

# Release v8.2.0

> **Release date:** 2026-08-07
> **Type:** Minor - Concurrent tenant web sessions.

## Summary

v8.2.0 keeps one tenant-scoped JWT per authorized tenant in the Laravel session. Users can switch tenants without
authenticating again, and separate tabs can operate in different tenant contexts through the URL or request header.

## Highlights

- Web login and 2FA store the complete tenant token portfolio returned by Caronte 4.1.0.
- `Caronte::getAvailableTenants()` and `Caronte::getCurrentTenantId()` expose safe UI context.
- Blade and React switchers preserve navigation state and never expose JWTs to the browser.
- Refresh and access failures affect only the selected tenant; logout clears the complete session.
- Existing single-token sessions, bearer authentication, and OIDC remain compatible.

## Validation

- PHPUnit: 154 tests, 524 assertions.
- TypeScript: `tsc --noEmit` passes.
- `git diff --check` passed.

---

# Release v8.0.0

> **Release date:** 2026-07-21
> **Type:** Major - Delegated authentication and consolidated group access.

## Summary

v8.0.0 adds delegated-login support and aligns group access with Caronte's
aggregate `/api/group` contract. It also removes legacy management routes and
plural group commands, so consumers must migrate to the resource-oriented
routes and singular `caronte:group:*` commands.

## Highlights

- Set `CARONTE_AUTH_ROUTES_ENABLED=false` and an absolute HTTPS
  `CARONTE_LOGIN_URL` to delegate login-producing routes to another group app.
- Use `GroupApi::showGroup()` or `caronte:group:show` to retrieve applications,
  assignable roles, API scopes, and tenant user mappings in one request.
- Use `caronte:group:users:roles:sync` for group role synchronization.
- User role synchronization previews additions and removals and requires
  confirmation; use `--force` for unattended execution.

## Breaking changes

- Removed `caronte:groups:roles:list`, `caronte:groups:users:list`, and the
  plural group role-sync alias.
- Removed legacy management role/user mutation routes.
- Group clients now call `/api/group` and
  `/api/group/users/{uri_user}/applications/{app_id}/roles`.

## Validation

- PHPUnit: 144 tests, 487 assertions.
- TypeScript: `tsc --noEmit` passes.

---

# Release v7.1.2

> **Release date:** 2026-07-15
> **Type:** Patch - Session token refresh persistence.

## Summary

v7.1.2 persists refreshed JWTs for session-authenticated web routes even when
the request expects a JSON response. Bearer-authenticated API requests retain
their existing behavior: refreshed tokens are returned through the
`X-User-Token` response header without creating or changing Laravel session
state.

---

# Release v7.1.1

> **Release date:** 2026-07-15
> **Type:** Patch - Multi-tenant session context propagation.

## Summary

v7.1.1 ensures that `caronte.session` binds Bee Hive's `TenantContext` from
the authenticated user's `id_tenant` in both multi-tenant and single-tenant
applications. Downstream Caronte HTTP clients can therefore propagate the
matching `X-Tenant-Id` header consistently.

Session tokens without a tenant now fail closed with HTTP 403 and are removed
from the session. Existing single-tenant mismatch validation is unchanged.

---

# Release v7.1.0

> **Release date:** 2026-07-15
> **Type:** Minor - Raw HTTP responses and automatic multipart uploads.

## Summary

v7.1.0 expands `CaronteHttpClient` for endpoints that do not use the SDK's
standard JSON response envelope. Applications can now download files, consume
binary or streamed responses, and upload files or stream resources while
reusing the existing Caronte authentication and HTTP configuration.

This release is additive. Existing `applicationRequest()` and `userRequest()`
signatures and parsed-response contracts remain unchanged.

## Highlights

- **Raw application requests** - `applicationRawRequest()` returns the original
  Laravel HTTP `Response` and supports the optional delegated user token.
- **Raw user requests** - `userRawRequest()` returns the original response while
  using the current user's JWT.
- **Automatic multipart transport** - payloads containing `UploadedFile`
  instances or stream resources are converted to multipart requests.
- **Nested upload support** - nested fields and lists of uploaded files retain
  their expected multipart field names.
- **Shared request behavior** - raw and JSON requests use the same tenant header,
  TLS verification, timeout, retry, query-string, and credential handling.

## Usage

Use a raw request when the response must not be decoded into the standard SDK
array:

```php
$response = $client->applicationRawRequest(
    'GET',
    'reports/monthly.pdf'
);

$contents = $response->body();
```

File payloads are detected automatically:

```php
$response = $client->applicationRawRequest(
    'POST',
    'documents',
    [
        'title' => 'Contract',
        'file' => $request->file('document'),
    ]
);
```

For endpoints authenticated as the current user:

```php
$response = $client->userRawRequest(
    'GET',
    'exports/latest'
);
```

## Upgrade requirements

No migrations or configuration changes are required. Consumers that do not use
the new raw-response or multipart APIs require no code changes.

## Compatibility

- Existing JSON requests continue to send `Accept: application/json` and return
  the normalized SDK response array.
- Raw requests default to `Accept: */*` and return
  `Illuminate\Http\Client\Response` without invoking `parseResponse()`.
- Application and group credentials remain mutually exclusive.
- Tenant context, optional delegated user tokens, query parameters, TLS options,
  timeouts, and retries behave consistently across parsed and raw requests.

## Validation

- Raw application and user authentication headers are covered by feature tests.
- Tenant propagation and delegated user-token trimming are covered.
- JSON parsing remains compatible with the existing response contract.
- Multipart conversion covers uploaded files, streams, nested fields, and file
  lists.
- Unsupported HTTP methods continue to raise `CaronteApiException`.
- The complete suite passes: 135 tests and 445 assertions.

# Release v7.0.0 "Ariadne"

> **Release date:** 2026-07-14
> **Type:** Major - Correction of the public tenant identifier contract.

## Summary

v7.0.0 restores Bee Hive's canonical `id_tenant` identifier throughout the SDK. The `tenant_id` contract published in v6.0.0 affected database columns, JWT claims, HTTP payloads, configuration, commands, and frontend bindings; all of those surfaces now consistently use `id_tenant`.

The security hardening and legacy removals delivered in v6 remain in place. This release changes the tenant-key name and provides a data-preserving corrective migration for the SDK's local user tables.

## Upgrade requirements

1. Back up the prefixed `Users` and `UsersMetadata` tables, then run `php artisan migrate` to rename their `tenant_id` columns to `id_tenant`.
2. Update user and protected API token issuers so the required tenant claim is `id_tenant`.
3. Update integrations, API payloads, DTOs, queries, and frontend bindings to consume `id_tenant` instead of `tenant_id`.
4. Use `caronte.tenancy.id_tenant` for single-tenant configuration. Keep using `CARONTE_TENANT_ID` in environment files.
5. Coordinate deployment with the Caronte server because v7 does not accept a `tenant_id` runtime alias.

## Validation

- The corrective migration renames existing tenant columns without copying or deleting their data.
- User and protected API access tokens require the `id_tenant` claim.
- Tenant-scoped user and metadata queries remain fail-closed.
- The feature suite passes with the corrected contract.

## Breaking changes

See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for the full migration checklist.

## Historical release notes

# Release v6.0.0 "Hermes"

> **Release date:** 2026-07-14
> **Type:** Major - Removal of legacy contracts and security hardening.

## Summary

v6.0.0 completes the transition to `tenant_id`, removes the remaining compatibility APIs, and hardens authentication boundaries. OIDC now validates state and nonce, browser callbacks are same-origin, tenant-owned local data fails closed without context, and user JWTs have a strict claim contract.

Application authentication now uses one credential per request. Applications without a configured group send `X-Application-Token`; grouped applications send only `X-Group-Token`. Group tokens retain `source_app_id` and `source_app_cn` for operational attribution, with integrity at group-secret level rather than non-repudiation between members.

## Upgrade requirements

1. Replace every runtime use of `id_tenant` with `tenant_id`.
2. Ensure issued user JWTs include `iss`, `aud`, `sub`, `jti`, `iat`, `nbf`, `exp`, `token_audience`, and `tenant_id`.
3. Update Caronte v6 endpoints to accept `X-Group-Token` as the sole credential for grouped applications.
4. Restrict post-login destinations to relative or same-origin URLs.
5. Back up `UsersMetadata` before migrating. Rows with no unambiguous tenant are discarded and counted in the application log.

## Validation

- OIDC state, PKCE verifier, and nonce are single-use and mandatory.
- Individual and group credentials are mutually exclusive; inbound requests containing both return `400`.
- Tenant-scoped helpers never fall back to unscoped queries.
- Legacy permissions, middleware aliases, nested user claims, and `auth_mode=legacy` are removed.

## Release v5.0.0

> **Release date:** 2026-06-26

Version 5.0.0 is the published major baseline from which v6 migrates. Its released persistence contract used `id_tenant`; v6 replaces that contract with Bee Hive-aligned `tenant_id` and removes the compatibility layers retained by 5.x.

# Release v4.6.0 "Hermes"

> **Release date:** 2026-07-14
> **Type:** Minor - Transition to JWT-first defaults, tenant-ID alignment, and deprecation signals.

---

## Summary

v4.6.0 "Hermes" strengthens the transition from legacy authentication modes to JWT-first operation while preparing the ground for v5.0.0's major breaking changes. This release introduces runtime deprecation signals, refines tenant-ID handling with automatic backfill, and establishes clear migration guidance for downstream applications.

The codename _Hermes_—god of transitions and communication—guides this release's essence: shepherding users toward modern JWT defaults, deprecating legacy adapters with visible warnings, and ensuring tenant-aware context resolution is solid and predictable.

## Highlights

- **JWT-First by Default** — `auth_mode` now defaults to `jwt`; `legacy` remains supported but flagged as deprecated
- **Runtime Deprecation Signals** — Compatibility adapters emit HTTP `Deprecation: true` headers and runtime warnings
- **Tenant Migration Automation** — Existing SDK user tables automatically backfill `tenant_id` and remove the legacy `id_tenant` column, aligning with Bee Hive tenancy conventions
- **Guided Path to v5.0.0** — Clear deprecation trail for nested user claims, legacy auth modes, permission-based protected API, and permission-named middleware

## Added

- **Runtime deprecation warnings and HTTP `Deprecation: true` signals** for compatibility adapters.
    - Deprecated code paths now emit visible warnings and set the `Deprecation: true` header on responses.
    - Helps teams identify legacy usage before v5.0.0 removal.

## Changed

- **`auth_mode` now defaults to `jwt`** — new installations prefer JWT tokens over legacy session modes.
    - `legacy` remains a temporary alias for backward compatibility but is flagged as deprecated.
- **Existing SDK user tables now backfill `tenant_id`** and remove the legacy physical `id_tenant` column.
    - Aligns with Bee Hive tenancy conventions.
    - Automatic migration during deployment ensures consistency across deployments.

## Deprecated

The following items will be **removed in SDK v5.0.0**:

- Nested `user` claim fallback
- `auth_mode=legacy`
- Protected API `permissions` aliases (use scopes instead)
- The `application_token` audience
- Legacy management mutations
- Permission-named middleware and commands

**Action required:** Review [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for detailed migration guidance.

## Fixed

- No changes.

## Removed

- No changes.

## Security

- No changes.

---

## Testing & Validation

- ✅ All feature tests pass
- ✅ CI pipeline green
- ✅ Runtime deprecation warnings verified
- ✅ Tenant-ID backfill migration tested
- ✅ Existing JWT workflows remain stable

## Migration Path

**For v4.6.0 users planning v5.0.0 upgrades:**

1. Deploy v4.6.0 and run migrations to backfill `id_tenant`
2. Monitor application logs for deprecation warnings
3. Update legacy `auth_mode=legacy` configurations to `auth_mode=jwt`
4. Replace permission-based protected API middleware with scope-based alternatives
5. Upgrade to v5.0.0 with confidence

See [BREAKING_CHANGES.md](BREAKING_CHANGES.md#v500) for the complete v5.0.0 migration guide.

## Related Documentation

- **[CHANGELOG.md](CHANGELOG.md)** — Full project history with all releases
- **[BREAKING_CHANGES.md](BREAKING_CHANGES.md)** — v5.0.0 breaking changes and migration guidance
- **[doc/deployment-instructions.md](doc/deployment-instructions.md)** — Installation and configuration steps

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.
See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

---

# Release v4.5.1

> **Release date:** 2026-06-10
> **Type:** Patch - Inertia response handling bug fix.

---

## Summary

v4.5.1 is a patch release addressing a bug in Inertia response handling when forwarding error responses with validation errors. This fix ensures that error messages, validation errors, and input data are properly preserved in the session before redirecting to the forwarded URL, while maintaining compatibility with Inertia's location-based response handling.

## Highlights

- ✅ **Fixed Inertia error response forwarding** — validation errors and input data now properly persist in session
- ✅ **Comprehensive test coverage** — new test case validates error preservation through Inertia redirects
- ✅ **No breaking changes** — fully backward-compatible patch

## Fixed

- **Inertia response handling for error redirects** — `CaronteResponse::redirect()` now correctly passes the response object to `Inertia::location()` for error responses with forwarded URLs. Previously, error messages and validation errors would be lost when Inertia::location() was called after the response was constructed. The fix restructures the response building to:
    1. First set error messages and input data on the response
    2. Then wrap the complete response with `Inertia::location()` if needed
       This ensures all session data (errors, messages, input) is properly flashed before the redirect.

## Added

- New feature test `test_inertia_forward_error_preserves_errors_and_input()` validates error preservation through Inertia redirects.

## Deprecated

- No changes.

## Removed

- No changes.

## Security

- No changes.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.
See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

---

# Release v4.5.0 "Suite"

> **Release date:** 2026-06-02
> **Type:** Minor - suite user-access API, CLI, and management UI.

---

## Summary

v4.5.0 "Suite" adds SDK support for Caronte application-group user management. Applications granted suite permissions by the server can list group roles, list tenant users, and synchronize non-root user roles for apps in the same suite.

## Highlights

- New `GroupApi` for `/api/application-groups/current` server endpoints.
- New CLI commands for suite role listing, suite user listing, and suite role synchronization.
- Management UI now includes a "Suite access" mode with app-grouped roles and root-role protection.
- Optional actor-token forwarding supports server-side audit attribution.

## Added

- `src/Api/GroupApi.php`.
- `caronte:groups:roles:list`.
- `caronte:groups:users:list`.
- `caronte:groups:users:roles:sync`.
- Management route `caronte.management.suite.users.applications.roles.sync`.

## Changed

- `CaronteHttpClient::applicationRequest()` now supports optional forwarded actor-token behavior for suite role synchronization.
- The interactive `caronte:admin` hub now includes suite role and user management actions.

## Deprecated

- No new deprecations in this release.

## Removed

- No removals in this release.

## Security

- The UI and CLI only send roles marked manageable by Caronte; reserved roles such as `root` stay out of suite sync payloads.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.
See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

---

# Release v4.4.0 "Atlas"

> **Release date:** 2026-05-24
> **Type:** Minor - middleware context-resolution refactor, role validation enhancement, and documentation restructuring.

---

## Summary

v4.4.0 "Atlas" strengthens middleware internals by extracting application, forwarded-user, and tenant resolution into dedicated resolver components while preserving route-level middleware contracts. It also improves role-management ergonomics by allowing dotted role names in both backend and frontend validation paths. Documentation was reorganized to provide a single middleware index plus one page per middleware alias for easier debugging and onboarding.

The codename _Atlas_ represents this release's role as structural support: clearer internal boundaries in middleware logic and a stronger documentation foundation for maintainers.

---

## Highlights

- **Resolver-based middleware internals** - `ResolveApplicationContext` now delegates responsibilities to focused support resolvers.
- **Validation consistency for role names** - dots are now accepted in role names across server and browser validation.
- **Operational middleware docs reworked** - middleware guidance moved to a split-per-alias structure with a central index.
- **Edge-case coverage improvements** - middleware feature tests now cover grouped-token header requirements and static tenant resolver wrapper behavior.

---

## Added

- `src/Support/CaronteApplicationContextResolver.php`
- `src/Support/CaronteForwardedUserContextResolver.php`
- `src/Support/CaronteTenantContextResolver.php`
- `doc/middleware.md`
- `doc/middleware/caronte.session.md`
- `doc/middleware/caronte.roles.md`
- `doc/middleware/caronte.application.md`
- `doc/middleware/caronte.protected-api-token.md`
- `doc/middleware/caronte.protected-api-scopes.md`
- `doc/middleware/caronte.app-token.md`
- `doc/middleware/caronte.app-permissions.md`

## Changed

- `src/Http/Middleware/ResolveApplicationContext.php` now orchestrates resolver services through constructor injection.
- `README.md` documentation index now points to `doc/middleware.md`.
- Documentation under `doc/` was refreshed and reorganized.

## Fixed

- Dotted role names are now accepted in:
    - `src/Support/ConfiguredRoles.php`
    - `resources/assets/js/caronte-management/roles.js`
    - `resources/views/management/roles/modals/create.blade.php`
- `tests/Feature/ConfigurationValidationTest.php` includes regression coverage for dotted role names.
- `tests/Feature/MiddlewareBehaviorTest.php` includes additional edge-case checks for application middleware behavior.

## Removed

- `doc/middleware-documentation.md` (replaced by index + per-middleware files).
- `doc/admin-handover.md`
- `doc/client-handover.md`

## Deprecated

- No new deprecations in this release.

## Security

- No direct security-surface changes in this release.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.
See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

---

# Release v4.3.0 "Relay"

> **Release date:** 2026-05-21
> **Type:** Minor - delegated user context support for application middleware.

---

## Summary

v4.3.0 "Relay" introduces delegated user-token handling for app-to-app protected routes. Application middleware can now require and validate a forwarded `X-User-Token`, bind the validated user context into the container, and use that context to resolve tenant identity more reliably. This release also includes a user-creation payload consistency fix and expanded middleware behavior coverage.

The codename _Relay_ reflects delegated context handoff: an application can securely relay user identity to downstream protected routes.

---

## Highlights

- **Forwarded user context binding** - new `CaronteForwardedUserContext` captures delegated user payload, tenant id, and token id.
- **New middleware mode** - `caronte.application` now supports `user_required`, and combinations like `tenant_required,user_required`.
- **Tenant resolution improvements** - tenant resolution can use forwarded-user tenant information when present.
- **Behavior hardening** - missing/invalid forwarded tokens now produce explicit `401` responses when user delegation is required.
- **Controller payload fix** - user creation now forwards `password_confirmation` from the request value.

---

## Added

- `src/Support/CaronteForwardedUserContext.php`.
- Forwarded user token parsing and container binding in `ResolveApplicationContext`.

## Changed

- `ResolveApplicationContext` now accepts variadic middleware modes and normalizes mode values.
- Tenant resolution flow now integrates forwarded user tenant fallback.
- Middleware feature tests expanded for delegated user/tenant mode combinations and error cases.

## Fixed

- `UserController::store()` now sends `password_confirmation` from request input to keep outgoing payloads consistent.

## Removed

- No removals in this release.

## Deprecated

- No new deprecations in this release.

## Security

- Explicit forwarded token validation paths reduce risk of accepting malformed delegated user context.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.
See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

---

# Release v4.2.0 "Bulwark"

> **Release date:** 2026-05-20
> **Type:** Minor — protected API scope and external-access token capabilities.

---

## Summary

v4.2.0 "Bulwark" introduces first-class protected API access management for host applications that expose APIs to external clients. The release adds dedicated protected API token and scope primitives, a focused scope-sync command, and updated middleware aliases to enforce scope-based access cleanly. Compatibility shims for legacy permission naming remain available so teams can migrate incrementally.

The codename _Bulwark_ reflects this release's purpose: strengthening the boundary between external API consumers and protected host resources.

---

## Highlights

- **Protected API access-token support** — dedicated token/context classes for validating external API clients.
- **Scope-first authorization model** — canonical protected API configuration and middleware naming built around scopes.
- **Operational migration path** — compatibility aliases retained temporarily for smoother upgrades from permission-oriented flows.
- **Delivery readiness documentation** — new client/admin handover guides for implementation, deployment, and support teams.

---

## Added

- `ScopeApi` and protected API support primitives (`CaronteProtectedApiAccessToken`, `CaronteProtectedApiAccessContext`, `ConfiguredScopes`).
- Middleware pair for protected API validation and scope checks.
- `caronte:protected-api:scopes:sync` Artisan command.
- New handover docs:
    - `doc/client-handover.md`
    - `doc/admin-handover.md`

## Changed

- Canonical configuration moved to `protected_api.scopes` (legacy permission naming remains as compatibility).
- Application/group JWT TTL controls and request header/audience handling were aligned to protected API flows.
- Service provider aliases and command surface updated to emphasize protected API naming.
- Documentation and feature tests expanded for protected API contract coverage.

## Fixed

- Reduced ambiguity between internal application permissions and external protected API scopes by introducing explicit protected API primitives and aliases.

## Breaking

- No breaking changes in this release.
- See migration recommendations in [BREAKING_CHANGES.md](BREAKING_CHANGES.md).

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.
See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

---

# Release v4.1.0 "Northstar"

> **Release date:** 2026-05-14
> **Type:** Major — tenant identity model and persistence semantics update.

---

## Summary

v4.1.0 "Northstar" unifies tenant identity semantics across the SDK by standardizing on `tenant_id` and enforcing tenant-aware composite primary keys for local user persistence. This release closes ambiguity around legacy tenant field naming, strengthens tenant isolation in metadata/user lookups, and aligns token-driven user sync with deterministic tenant-scoped identity.

The codename _Northstar_ reflects the release goal: a single, reliable reference point for tenant identity across all persistence and helper paths.

---

## Highlights

- **Canonical tenant key** — package internals now consistently use `tenant_id` in models, helpers, command output, and local sync.
- **Composite keys for tenant-safe identity** — `Users` and `UsersMetadata` now rely on tenant-aware composite primary keys.
- **Migration compatibility path** — upgrade migrations backfill missing `tenant_id` values from legacy data/default tenancy context when possible.
- **Tenant-scoped metadata/user access hardening** — helper and relationship queries now consistently constrain by tenant.

---

## Added

- Composite-key model configuration via `HasCompositePrimaryKey` for user and metadata entities.
- Migration logic to fill missing `tenant_id` values during upgrades.

## Changed

- `Caronte::updateUserData()` now upserts users/metadata against composite tenant-scoped identity.
- `CaronteUserToken` explicit payload mapping and downstream flows align with `tenant_id`.
- `CaronteUserHelper` and `caronte:users:list` output/queries are aligned on `tenant_id`.

## Fixed

- Reduced risk of cross-tenant data collisions in local user metadata persistence and lookup paths.
- Improved consistency of tenant key usage across runtime, migrations, and CLI surfaces.

## Breaking

- Host custom code that still references `id_tenant` or assumes `uri_user` alone is globally unique must be updated.
- See migration steps in [BREAKING_CHANGES.md](BREAKING_CHANGES.md).

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.
See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

---

# Release v3.6.0 "Keystone"

> **Release date:** 2026-05-13
> **Type:** Minor — new backwards-compatible tenancy capabilities.

---

## Summary

v3.6.0 "Keystone" introduces first-class single-tenant runtime support while preserving the existing multi-tenant behavior. This release adds explicit tenancy mode configuration, centralizes tenant resolution in a dedicated support helper, and hardens tenant consistency checks across auth and middleware paths.

The codename _Keystone_ reflects the goal of this release: making tenant context the structural anchor for every authenticated request.

---

## Highlights

- **Single-tenant runtime mode** — configure `CARONTE_TENANCY_MODE=single` with `CARONTE_TENANT_ID` to run tenant-pinned applications without custom middleware forks.
- **Shared tenancy helper** — new `CaronteTenancy` support class standardizes tenant-mode and tenant-id resolution across SDK internals.
- **Tenant mismatch enforcement** — auth and request middleware now fail fast with `403` when tenant context is inconsistent.
- **Tenant header propagation** — user/application API calls now forward `X-Tenant-Id` when available to keep server-side checks aligned with resolved tenant context.

---

## Added

- Tenancy configuration support for `multi` and `single` modes.
- New `Ometra\Caronte\Support\CaronteTenancy` helper for tenant resolution and validation.

## Changed

- `AuthController`, `ResolveApplicationContext`, and `ValidateUserToken` now enforce tenant consistency.
- `CaronteHttpClient` now includes tenant context through `X-Tenant-Id` when resolved.
- Documentation updates in deployment/routes guides for tenancy operation details.

## Fixed

- Tenant mismatch flows now return explicit forbidden responses instead of allowing ambiguous context pass-through.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.
See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

---

# Release v3.5.0 "Waypoint"

> **Release date:** 2026-05-13
> **Type:** Minor — new backwards-compatible login flow improvements.

---

## Summary

v3.5.0 "Waypoint" improves the tenant-selection sign-in journey for shared users. When Caronte responds with `tenant_selection_required`, the SDK now stores a short-lived pending login context and allows users to complete tenant selection without re-entering their password. The login experiences in both Blade and Inertia were updated to reflect this second-step mode clearly and safely.

The codename _Waypoint_ reflects the new guided checkpoint between credential validation and final tenant-aware authentication.

---

## Highlights

- **Pending login context for tenant selection** — retains email + selection token temporarily to complete the next step cleanly.
- **Improved login UX in both render modes** — email is prefilled/read-only and password is omitted during tenant-selection step.
- **Auth API payload extension** — `tenant_selection_token` now forwarded by `AuthApi::login()` when present.
- **Expanded test coverage** — feature tests validate tenant-selection redirects, token forwarding, and password-less second-step login.

---

## Added

- Pending tenant-selection login context support in the authentication controller flow.

## Changed

- Blade and Inertia login screens now render a tenant-selection-specific form state.
- `AuthApi::login()` signature and payload handling now include optional `tenant_selection_token`.
- Conflict handling paths now preserve tenant-selection data consistently across web and JSON requests.

## Fixed

- Eliminated password re-entry requirement during tenant-selection retry after `409 tenant_selection_required`.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.
See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

---

# Release v3.4.0

> **Release date:** 2026-05-11
> **Type:** Minor — new tenant management features added backwards-compatibly.

---

## Summary

v3.4.0 introduces first-class tenant management capabilities in the SDK command surface. This release adds a dedicated `TenantApi` client, two new tenant-focused Artisan commands, and integrates those commands into the interactive admin menu. It also refines the existing users list experience by introducing a clearer `--app-users` option while keeping `--all` as a deprecated compatibility alias.

No breaking changes are introduced.

---

## Highlights

- **New tenant API client** — `TenantApi` now provides tenant listing and detail retrieval helpers.
- **New tenant CLI commands** — `caronte:tenants:list` and `caronte:tenants:show` for operational workflows.
- **Admin menu integration** — tenant commands are available via `caronte:admin` interactive flow.
- **Improved users list semantics** — explicit `--app-users` flag with `--all` preserved as deprecated alias.

---

## Added

### Tenant API

New API client methods:

- `TenantApi::listTenants()`
- `TenantApi::showTenant()`

### Tenant CLI

New Artisan commands:

- `caronte:tenants:list`
- `caronte:tenants:show`

These commands are also exposed from the interactive `caronte:admin` menu.

---

## Changed

- `caronte:users:list` now supports `--app-users` as the canonical option.
- `--all` remains available as a deprecated alias for backwards compatibility.
- Users list output now includes tenant information.
- List command forwarding to the API now uses the correct `app_users` parameter.
- Command behavior tests were expanded to cover tenant command behavior and app-users option handling.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for complete project history.

---

# Release v3.3.1

> **Release date:** 2026-05-11
> **Type:** Patch — backwards-compatible migration compatibility fix.

---

## Summary

v3.3.1 delivers a targeted migration compatibility fix for host applications running newer Laravel versions. The `users_metadata_table` migration no longer depends on Doctrine DBAL-specific schema-manager APIs to inspect primary key metadata. Instead, it uses Laravel schema-builder index introspection when available and falls back to native MySQL/MariaDB index queries when needed.

This patch prevents migration/runtime issues in environments where deprecated Doctrine schema-manager methods are unavailable, while preserving the expected composite primary key behavior for `UsersMetadata`.

---

## Highlights

- **Laravel 10/11/12-safe migration introspection** — no hard dependency on removed Doctrine schema-manager APIs.
- **Driver-aware fallback path** — uses `SHOW INDEX` for MySQL/MariaDB when schema-builder index APIs are not present.
- **Primary key normalization retained** — still enforces `['uri_user', 'scope', 'key']` as the composite primary key.

---

## Fixed

### Users metadata migration compatibility

`database/migrations/user_metadata_table.php` now retrieves current primary-key columns through a compatibility-aware strategy:

1. Uses `Schema::getConnection()->getSchemaBuilder()->getIndexes()` when available.
2. Falls back to `SHOW INDEX ... WHERE Key_name = 'PRIMARY'` for MySQL/MariaDB.
3. Avoids DBAL-only schema-manager dependencies that can fail on newer Laravel versions.

No host application code changes are required.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for the complete project history.

---

# Release v3.3.0 "Chronos"

> **Release date:** 2026-05-07
> **Type:** Minor — new features added backwards-compatibly. No breaking changes.

---

## Summary

v3.3.0 "Chronos" delivers three focused improvements to the Caronte SDK: **configurable token clock skew**, a **GitHub Actions CI pipeline**, and a **frontend TypeScript migration** with new legacy-compatible management routes. Clock-skew tolerance closes an operational gap for multi-host deployments where clocks are not perfectly synchronised. The CI pipeline brings automated PHP and TypeScript quality checks to every push and PR. Migrating the React UI to TypeScript improves long-term maintainability and enables compile-time safety for SDK data shapes.

The codename _Chronos_ — the Greek personification of time — reflects the clock-skew theme and the automated, time-orchestrated CI jobs that now guard each commit.

---

## Highlights

- **Configurable clock skew** — `CARONTE_TOKEN_CLOCK_SKEW_SECONDS` (default `60`) lets deployments tolerate small clock differences without token validation failures.
- **GitHub Actions CI** — PHP (PHPUnit) and TypeScript (`tsc --noEmit`) jobs run automatically on push and pull requests.
- **TypeScript frontend** — all React management pages are now `.tsx` with a shared `types.ts` file; no host-application changes required.
- **Legacy management routes** — `users.list` and `users.roles.list` JSON endpoints added for backwards-compatible integrations.
- **Improved Blade views** — create-user modal includes a temporary password field; roles partial is now resilient to missing variables.

---

## Added

### Configurable Token Clock Skew

Set `CARONTE_TOKEN_CLOCK_SKEW_SECONDS` in your environment (or override `token_clock_skew_seconds` in `config/caronte.php`) to allow a leeway window during `iat`/`nbf` claim validation:

```dotenv
# .env
CARONTE_TOKEN_CLOCK_SKEW_SECONDS=120   # allow up to 2 minutes of clock drift
```

The default is `60` seconds — the same leeway applied silently in previous releases via a hard-coded constant. No host-application changes are required if the default is acceptable.

### GitHub Actions CI Workflow

`.github/workflows/ci.yml` runs two parallel jobs on every push to `main`/`dev` and on pull requests:

| Job        | Tool           | What it checks                      |
| ---------- | -------------- | ----------------------------------- |
| PHP        | PHPUnit        | Full test suite via `composer test` |
| TypeScript | `tsc --noEmit` | Type correctness of frontend assets |

### Frontend TypeScript Migration

All management React pages have been migrated from `.jsx` to `.tsx`. A new `resources/js/types.ts` file provides strongly-typed interfaces for SDK data structures:

```ts
// resources/js/types.ts (excerpt)
export interface CaronteUser {
    uuid: string;
    name: string;
    email: string;
    roles: CaronteRole[];
    // ...
}
```

`tsconfig.json` and `package.json` (TypeScript dev-dependencies) are included so the type-check step works with `npm ci && npm run typecheck` out of the box.

### Legacy Management Routes

New named routes for backwards-compatible JSON access:

| Route name         | Method | URI                                      |
| ------------------ | ------ | ---------------------------------------- |
| `users.list`       | GET    | `/caronte/management/users`              |
| `users.roles.list` | GET    | `/caronte/management/users/{user}/roles` |

`UserController` exposes `list()`, `listRoles()`, and legacy `update()`/`delete()` wrapper methods. `RoleController` redirects unsupported legacy mutations with a clear error response.

---

## Changed

- `ManagementController` now passes configured roles to index/dashboard views.
- Blade `create.blade.php` modal includes a temporary password field.
- `roles-checkboxes.blade.php` partial uses an `availableRoles` fallback to prevent undefined-variable errors.
- `doc/routes-documentation.md` updated to document current vs legacy routes.
- `doc/deployment-instructions.md` notes the new TSX asset compilation step.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for the complete project history.
No breaking changes in this release. See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance on previous versions.

---

# Release v3.2.1

> **Release date:** 2026-05-07
> **Type:** Patch — backwards-compatible security improvement.

---

## Summary

v3.2.1 delivers a targeted security hardening to the Caronte SDK's HTTP layer. Every user-authenticated API call made through `CaronteHttpClient::userRequest()` now includes both the `X-Application-Token` and the `X-User-Token` headers. Previously only `X-User-Token` was sent, which meant the Caronte server could not simultaneously verify the calling application's identity during user-context operations. This patch closes that gap without requiring any host-application changes.

---

## Highlights

- **Dual-token user requests** — `CaronteHttpClient::userRequest()` now sends `X-Application-Token` alongside `X-User-Token`, strengthening the trust chain for all user-context API calls.
- **Test coverage updated** — `AuthContractTest` updated to assert both tokens are present in user requests.

---

## Changed

### `X-Application-Token` Added to User Requests

`CaronteHttpClient::userRequest()` previously sent only the `X-User-Token` header. Starting with v3.2.1 it also sends the `X-Application-Token`, derived from the configured application credentials.

**Before (≤ v3.2.0):**

```php
// Only user token was forwarded
'X-User-Token' => Caronte::getToken()->toString()
```

**After (v3.2.1):**

```php
// Both application and user tokens are forwarded
'X-Application-Token' => $this->makeApplicationToken(),
'X-User-Token'        => Caronte::getToken()->toString(),
```

No configuration changes or host-application code changes are required. The `CARONTE_APP_CN` and `CARONTE_APP_SECRET` credentials already in use for app-level requests are reused here.

---

## Full History

See [CHANGELOG.md](CHANGELOG.md) for the complete project history.

---

# Release v3.2.0 "Hermes"

> **Release date:** 2026-05-06
> **Type:** Minor — new features added backwards-compatibly.
> One internal class removal requires attention if referenced directly. See [BREAKING_CHANGES.md](BREAKING_CHANGES.md).

---

## Summary

v3.2.0 "Hermes" delivers three focused improvements to the Caronte SDK: **multi-tenant login support**, **phase-2 (flat) JWT claim parsing**, and a **new provisioning API client**. Host applications with multi-tenant user bases can now present a tenant picker at login time without any custom controller logic. The phase-2 JWT changes introduce a flatter claim structure that coexists transparently with the legacy `user` nested claim — no migration required for existing tokens. Server-side tenant provisioning is now first-class through the new `ProvisioningApi`.

The codename _Hermes_ — the messenger god who moved freely between realms and delivered messages between mortals and gods — captures the theme of this release: bridging multiple tenant realms at login, carrying phase-2 claims between client and server, and provisioning new tenants through a dedicated message channel.

---

## Highlights

- **Multi-tenant login** — automatic tenant picker when a user belongs to multiple tenants; 409 `tenant_selection_required` handled gracefully out of the box.
- **Phase-2 JWT claims** — `CaronteUserToken::userPayload()` reads flat top-level claims (`sub`, `tenant_id`, `roles`, `metadata`) while falling back to legacy nested `user` claim.
- **`ProvisioningApi`** — `provisionTenant()` triggers server-side tenant provisioning; `AuthApi` updated for new two-factor and logout endpoints.
- **`.editorconfig` / `.gitattributes`** — repository-wide tooling rules for consistent formatting and line endings.
- **`RouteMode` removed** — `Ometra\Caronte\Support\RouteMode` replaced by inline `Request` detection. See [BREAKING_CHANGES.md](BREAKING_CHANGES.md).

---

## Added

### Multi-Tenant Login Flow

When the Caronte server returns HTTP 409 `tenant_selection_required`, the SDK now handles it automatically:

| Component                           | Responsibility                                                |
| ----------------------------------- | ------------------------------------------------------------- |
| `AuthController`                    | Reads `tenant_options` from session; exposes them to view/SPA |
| `AuthApi::login()`                  | Accepts optional `$tenantId`; includes it in sign-in payload  |
| `CaronteResponse::conflict()`       | Returns 409 responses (JSON or redirect) with tenant data     |
| `CaronteResponse::redirectErrors()` | Shapes session error data before redirect                     |
| React login form                    | Renders tenant dropdown when `tenant_options` present         |
| Blade login view                    | Renders tenant dropdown when `tenant_options` present         |

### Phase-2 JWT Claims

`CaronteUserToken::userPayload()` now prefers the phase-2 flat claim structure:

```json
{
    "sub": "user-uuid",
    "tenant_id": "tenant-uuid",
    "roles": ["admin"],
    "metadata": {}
}
```

Falls back to the legacy nested structure if `sub` is absent:

```json
{
    "user": {
        "id": "user-uuid",
        "tenant_id": "tenant-uuid",
        "roles": ["admin"]
    }
}
```

No code changes required in host applications for either structure.

### Provisioning API

```php
// New ProvisioningApi client
$api = new ProvisioningApi();
$api->provisionTenant($tenantId);
```

---

## Changed

- `CaronteUserHelper` metadata lookup now uses the `DB` facade directly.
- `AuthApi` updated for new two-factor endpoints and includes the user token on logout calls.
- Request-based JSON/web detection inlined; `RouteMode` static helper removed.
- Route prefix and login URL config defaults updated.
- Documentation updated: `api-documentation.md`, `business-logic-and-core-processes.md`, `deployment-instructions.md`, `routes-documentation.md`, `tests-documentation.md`.

---

## Removed

- `Ometra\Caronte\Support\RouteMode` — see [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration.

---

## Full History

- [CHANGELOG.md](CHANGELOG.md) — complete project history.
- [BREAKING_CHANGES.md](BREAKING_CHANGES.md) — migration guides for all breaking changes.

---

# Release v3.1.0 "Aegis"

> **Release date:** 2026-05-04
> **Type:** Minor — new features added backwards-compatibly.
> One dependency change requires attention. See [BREAKING_CHANGES.md](BREAKING_CHANGES.md).

---

## Summary

v3.1.0 "Aegis" extends the Caronte SDK with two major capability pillars: **OpenID Connect (OIDC) federated authentication** and **application-group permission controls**. Host applications can now authenticate users through a standard OIDC/OAuth 2.0 authorization code flow with PKCE, validate ID tokens against a live JWKS endpoint, and enforce fine-grained server-to-server permissions via group-level application tokens — all without changing a single existing integration point.

The codename _Aegis_ (the divine shield borne by Zeus and Athena) reflects the expanded protective surface this release adds: standards-based identity federation, group-scoped credential boundaries, and declarative permission synchronisation, layered on top of the solid foundation laid by v3.0.0 "Archon".

---

## Highlights

- **OIDC / OAuth 2.0 login** — full authorization code + PKCE flow, JWKS-backed token validation, `dual` mode for gradual migration from legacy JWT.
- **Application-group tokens** — `CARONTE_APPLICATION_GROUP_ID` / `CARONTE_APPLICATION_GROUP_SECRET` credentials enable group-scoped inter-service authentication and permission assertions.
- **Permission sync command** — `caronte:permissions:sync [--dry-run]` declaratively synchronises your configured permissions to the Caronte server.
- **Two new middleware** — `caronte.app-token` and `caronte.app-permissions` for validating application-access tokens and asserting permissions on routes.
- **BeeHive 3.0 required** — `equidna/bee-hive` constraint raised to `^3.0`.

---

## Added

### OpenID Connect Support

A complete `Oidc/` module ships with this release:

| Class                | Responsibility                                               |
| -------------------- | ------------------------------------------------------------ |
| `OidcClient`         | Builds authorization URLs, exchanges codes, refreshes tokens |
| `OidcTokenValidator` | Validates OIDC ID tokens against JWKS                        |
| `JwksCache`          | Fetches and caches the JWKS document (configurable TTL)      |
| `Jwk`                | Parses JWK entries; verifies RS256 signatures                |
| `Pkce`               | Generates PKCE `code_verifier` / S256 `code_challenge` pairs |
| `Base64Url`          | URL-safe Base64 encoding helper                              |

**`OidcAuthController`** handles the full flow:

- `GET /caronte/oidc/authorize` — redirects to the OIDC provider.
- `GET /caronte/oidc/callback` — exchanges the authorization code and stores the token.
- `POST /caronte/oidc/logout` — terminates the OIDC session.

**Auth modes** (configured via `CARONTE_AUTH_MODE`):

- `legacy` (default) — existing JWT flow, no OIDC.
- `oidc` — OIDC exclusively; legacy tokens rejected.
- `dual` — accepts both; validator selected from token `kid` header.

**`Caronte::getUser()`** now falls back to OIDC standard claims (`sub`, `name`, `email`, `email_verified`) when the legacy `user` claim is absent.

### Application-Group Tokens & Permission Controls

- **`CaronteApplicationAccessToken`** — generates and validates tokens signed with the group credentials.
- **`CaronteApplicationAccessContext`** — context object bound in the DI container after successful group-token validation; carries resolved permissions.
- **`ValidateApplicationAccessToken`** (`caronte.app-token`) — validates `X-Application-Access-Token` header.
- **`ValidateApplicationAccessPermissions`** (`caronte.app-permissions`) — asserts required permissions on the bound context.

### Permission Synchronisation

- **`PermissionApi`** — API client for the Caronte `/permissions` endpoints.
- **`ConfiguredPermissions`** — encapsulates `config('caronte.permissions')` logic; always injects `root`.
- **`caronte:permissions:sync [--dry-run]`** — Artisan command; `--dry-run` previews diffs without applying.

### New Configuration Keys

```php
// config/caronte.php
'application_group_id'     => env('CARONTE_APPLICATION_GROUP_ID', ''),
'application_group_secret' => env('CARONTE_APPLICATION_GROUP_SECRET', ''),
'auth_mode'                => env('CARONTE_AUTH_MODE', 'legacy'), // legacy | oidc | dual
'oidc' => [
    'issuer'         => env('CARONTE_OIDC_ISSUER', ...),
    'client_id'      => env('CARONTE_OIDC_CLIENT_ID', ...),
    'client_secret'  => env('CARONTE_OIDC_CLIENT_SECRET', ''),
    'redirect_uri'   => env('CARONTE_OIDC_REDIRECT_URI', ''),
    'scopes'         => env('CARONTE_OIDC_SCOPES', 'openid profile email'),
    'jwks_cache_ttl' => (int) env('CARONTE_OIDC_JWKS_CACHE_TTL', 3600),
],
```

---

## Changed

- `equidna/bee-hive` constraint: `>=2.0` → `^3.0`.
- `Caronte::syncUser()` now binds a `TenantContext` during local DB operations.
- `CaronteApplicationToken` updated to match and emit group context.
- `CaronteUserToken` updated to support group-signed tokens and multi-mode validation.
- `ResolveApplicationContext` updated to populate group context when present.
- Documentation suite fully updated: `api-documentation.md`, `artisan-commands.md`, `business-logic-and-core-processes.md`, `routes-documentation.md`, `tests-documentation.md`.
- `README.md` extended with Token Types reference and OIDC quick-start.

---

## Dependency Note: BeeHive 3.0

The `equidna/bee-hive` package is now required at `^3.0`. This is the only change that may require action:

```bash
composer require equidna/bee-hive:^3.0
```

See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for full migration steps.

---

## Full Changelog

See [CHANGELOG.md](CHANGELOG.md) for the complete project history.

## Migration Guide

See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for step-by-step migration instructions.
