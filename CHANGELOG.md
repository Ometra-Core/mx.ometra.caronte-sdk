# Changelog

All notable changes to the Caronte Client package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [8.6.0] - 2026-08-18

### Added

- `caronte:install` command to publish configuration, required UI assets, and migrations in one step.
- `caronte:ui:update` command to refresh published assets and Inertia components after an SDK upgrade.
- Package-owned default Caronte background, logo, and footer branding assets.
- Feature coverage for installation, UI updates, command registration, and local branding defaults.

### Changed

- Authentication views now use package-published branding assets by default instead of remote URLs.
- Authentication typography now consistently uses the imported Inter font.
- Deployment and upgrade documentation now treats UI assets as required resources.

### Removed

- Empty legacy `resources/assets/caronte.css` placeholder.

## [8.5.0] - 2026-08-18

### Added

- Opt-in `application_group` portal access mode for applications that must authenticate suite members without a base application role.
- Shared `CarontePageProps` TypeScript contract.

### Changed

- React and Blade tenant switchers now render only when at least two authorized tenants are available.
- Tenant switcher documentation now standardizes published imports, thin visual wrappers, and full URL preservation.

### Security

- Group portal access still requires a correctly signed application-group user token with matching audience, group identifier, and tenant context.

## [8.4.0] - 2026-08-17

### Added

- Configurable authentication background, Caronte logo, and footer logo URLs for Blade and Inertia consumers.

### Changed

- Authentication pages now use Caronte's dark translucent panel, photographic background, gold accent, responsive
  behavior, and typography while management pages retain their existing light surface.
- The Blade and React login contracts now render the same configurable Caronte brand mark.

## [8.3.1] - 2026-08-12

### Changed

- `BlockLoginRouteWhenDisabled` now builds delegated login callback fallback with `caronte.routes.success_url` first, then `APP_URL`.

### Fixed

- When both `caronte.routes.success_url` and `APP_URL` are empty, delegated login redirects no longer append `callback_url`.

### Added

- Feature-test coverage for blocked-login redirects with callback fallback present and absent.

## [8.3.0] - 2026-08-11

### Added

- Login route blocking middleware (`BlockLoginRouteWhenDisabled`) to prevent access to the login form when it is provided by an external service.

## [8.2.0] - 2026-08-07

### Added

- Server-side tenant token portfolios for web sessions, with request-scoped selection by `id_tenant` query or
  `X-Tenant-Id` header.
- Public tenant discovery APIs and shared Inertia properties for available and current tenants.
- Accessible Blade and React tenant switcher components with independent browser-tab navigation.

### Changed

- Web password and 2FA login request all eligible tenant tokens while API bearer and OIDC flows retain their
  existing contracts.
- Token refresh replaces only the selected tenant entry, and web logout revokes and clears the complete portfolio.
- Legacy single-token sessions remain supported as a compatibility fallback.

### Security

- Tenant selectors are validated against the server-side portfolio; conflicting or unavailable tenant overrides
  fail closed with HTTP 403.
- Business payload fields named `id_tenant` are not treated as navigation context.

## [8.0.0] - 2026-07-21

### Added

- Added delegated-login mode through `CARONTE_AUTH_ROUTES_ENABLED=false`; logout and authenticated-session inspection remain local.
- Added the aggregate `GroupApi::showGroup()` contract and the `caronte:group:show` command.
- User role synchronization now previews the complete replacement set and requires confirmation unless `--force` is supplied.

### Changed

- Group access now uses the Caronte v8 `/api/group` contract for the API client, management UI, and Artisan commands.
- Renamed group role synchronization to `caronte:group:users:roles:sync`.
- Management group-role synchronization now uses `/users/{uri_user}/applications/{app_id}/roles`.

### Removed

- Removed the split `caronte:groups:roles:list` and `caronte:groups:users:list` commands and all plural group command aliases.
- Removed legacy management role and user mutation routes; consumers must use the resource-oriented routes.

## [7.1.2] - 2026-07-15

### Fixed

- Refreshed JWTs are now persisted for session-authenticated web routes even when the request expects JSON.
- API Bearer token refresh continues to return `X-User-Token` without creating or changing Laravel session state.

## [7.1.1] - 2026-07-15

### Fixed

- `caronte.session` now binds `TenantContext` from the authenticated user's `id_tenant` in multi-tenant mode.
- Session-authenticated requests now fail closed with HTTP 403 when the user token has no tenant context.

## [7.1.0] - 2026-07-15

### Added

- `CaronteHttpClient::applicationRawRequest()` and `userRawRequest()` return unparsed Laravel HTTP responses for downloads, streams, and other non-JSON endpoints.
- Shared HTTP transport now supports automatic multipart payloads containing uploaded files, streams, nested fields, and file lists.

### Changed

- JSON and raw requests now share one transport path while preserving the existing authentication, tenant, timeout, retry, and error contracts.
- Method calls with more than three arguments are formatted with one argument per line for consistency and readability.

## [7.0.0] - 2026-07-14 "Ariadne"

### Changed

- Restored Bee Hive's canonical `id_tenant` identifier across JWT claims, API payloads, configuration, persistence, command output, management views, and frontend bindings.
- Local `Users` and `UsersMetadata` models and composite primary keys now use `id_tenant`.
- Single-tenant configuration now reads `caronte.tenancy.id_tenant`; the `CARONTE_TENANT_ID` environment variable remains unchanged.

### Fixed

- Added a corrective migration that renames the short-lived `tenant_id` columns back to `id_tenant` in `Users` and `UsersMetadata` without copying or discarding data.
- Realigned the SDK contract with Bee Hive 3.x after the incompatible tenant-key change published in v6.0.0.

### Removed

- Removed the v6 `tenant_id` contract. Consumers must use `id_tenant`; no runtime alias is provided.

### Security

- Tenant-scoped lookups remain fail-closed and continue to include the canonical tenant key in user and metadata queries.

## [6.0.0] - 2026-07-14 "Hermes"

### Changed

- Consolidated Blade/Inertia rendering under `ui.use_inertia`, configured only through `CARONTE_USE_INERTIA`.
- Removed the unused `management.features.profile_pictures` flag; `management.features.metadata` remains because it gates both metadata endpoints and UI controls.
- Grouped token timing settings under `token.ttl_seconds`, `token.clock_skew_seconds`, and `token.refresh_leeway_seconds`; application and application-group tokens share the TTL configured through `CARONTE_TOKEN_TTL_SECONDS`.
- Grouped route configuration under `routes.prefix`, `routes.login_url`, and `routes.success_url` while retaining the existing environment variable names.
- Made issuer validation mandatory for user, application, application-group, protected API, and OIDC tokens.
- Application requests now send exactly one credential: `X-Application-Token` for individual applications or `X-Group-Token` for configured groups.
- User JWTs now require issuer, audience, subject, token id, temporal claims, token audience, and the canonical `tenant_id` claim.
- Browser callback URLs are restricted to relative or same-origin destinations.
- Local user and metadata lookups now fail closed when tenant context is unavailable.

### Fixed

- OIDC callbacks now require non-empty state, PKCE verifier, and nonce values and validate the ID-token nonce.
- The v6 tenant migration now backfills `UsersMetadata.tenant_id`, resolves unambiguous user ownership, and discards only orphaned or ambiguous metadata.

### Removed

- Removed the `id_tenant` runtime contract; `tenant_id` is now the only supported tenant identifier.
- Removed the nested JWT `user` claim fallback and `auth_mode=legacy`.
- Removed permission-based protected API configuration, claims, APIs, middleware aliases, context aliases, and sync command.
- Removed legacy management mutation routes, controller wrappers, middleware, and Blade partials.
- Removed the deprecated `--all` option from `caronte:users:list`.
- Removed `CARONTE_MANAGEMENT_USE_INERTIA` and the duplicate top-level `use_inertia` configuration.
- Removed `CARONTE_APPLICATION_TOKEN_TTL_SECONDS` and `CARONTE_APPLICATION_GROUP_TOKEN_TTL_SECONDS`.
- Removed `CARONTE_ENFORCE_ISSUER` and the `enforce_issuer` configuration switch.

### Security

- Closed open-redirect, OIDC replay, unsigned application-token attribution, and unscoped tenant-query paths.

## [5.0.0] - 2026-06-26

### Changed

- Published the 5.0 major baseline with the `id_tenant` tenant contract and the compatibility paths subsequently removed by v6.0.0.

## [4.6.0] - 2026-07-14 "Hermes"

### Added

- Runtime deprecation warnings and HTTP `Deprecation: true` signals for compatibility adapters.

### Changed

- `auth_mode` now defaults to `jwt`; `legacy` remains a temporary alias.
- Existing SDK user tables now backfill `tenant_id` and remove the legacy physical `id_tenant` column, matching Bee Hive tenancy conventions.

### Deprecated

- Nested `user` claim fallback, `auth_mode=legacy`, protected API `permissions` aliases, the `application_token` audience, legacy management mutations, and permission-named middleware/commands will be removed in SDK 5.

### Security

- No changes.

## [4.5.1] - 2026-06-10

### Fixed

- **Inertia response handling for error redirects** — `CaronteResponse::redirect()` now correctly passes the response object to `Inertia::location()` for error responses with forwarded URLs, ensuring validation errors and input data are properly preserved in session before redirect.

### Security

- No changes.

## [4.5.0] - 2026-06-02 "Suite"

### Added

- **GroupApi** for Caronte suite access endpoints: `showGroupRoles()`, `showGroupUsers()`, and `syncGroupUserRoles()`.
- **Suite access CLI commands**: `caronte:groups:roles:list`, `caronte:groups:users:list`, and `caronte:groups:users:roles:sync`.
- **Management UI Suite access mode** that lists tenant users, shows suite apps/roles grouped by application, and blocks non-manageable roles such as `root`.
- **Optional actor token forwarding** for suite role sync through `X-User-Token`.
- **Feature coverage** for GroupApi headers/payloads, CLI commands, management route registration, and suite UI rendering.

### Changed

- `CaronteHttpClient::applicationRequest()` can now forward an optional user token while preserving existing API call signatures.
- The interactive `caronte:admin` hub now includes suite role and user management actions.

### Security

- Suite role sync validates selected roles against the server-provided manageable group role catalog before sending the request.

### Deprecated

- No changes.

### Removed

- No changes.

## [4.4.0] - 2026-05-24 "Atlas"

### Added

- **Application-context resolver components** — introduced `CaronteApplicationContextResolver`, `CaronteForwardedUserContextResolver`, and `CaronteTenantContextResolver` to isolate token, forwarded-user, and tenant resolution concerns.
- **Middleware documentation index and split pages** — added a root middleware index (`doc/middleware.md`) and one document per middleware alias in `doc/middleware/` for focused operations and debugging guidance.

### Changed

- **ResolveApplicationContext orchestration** — `ResolveApplicationContext` now delegates context resolution to dedicated support resolvers via constructor injection while preserving middleware behavior.
- **Backward-compatibility wrapper retained** — static `ResolveApplicationContext::resolveTenant()` remains available and now delegates to `CaronteTenantContextResolver`.
- **Documentation structure refresh** — documentation set was reorganized and updated, including middleware docs extraction and README documentation-index link updates.

### Fixed

- **Role naming validation parity (backend + frontend)** — role names now accept dots (`.`) in addition to letters, numbers, underscores, and hyphens in both `ConfiguredRoles` validation and management UI client-side checks.
- **Role creation guidance updated** — management modal helper text now reflects accepted role-name characters.
- **Coverage for middleware edge cases** — feature tests now include grouped-token missing-application-token rejection and static tenant resolver wrapper behavior.

### Removed

- **Operational handover documents removed** — `doc/admin-handover.md` and `doc/client-handover.md` were removed from the documentation tree.

### Deprecated

- No changes.

### Security

- No changes.

## [4.3.0] - 2026-05-21 "Relay"

### Added

- **Forwarded user-token context for application routes** - `ResolveApplicationContext` now supports a `user_required` mode that validates `X-User-Token` and binds a request-scoped `CaronteForwardedUserContext` containing user payload, tenant id, and token id (`jti`).
- **Forwarded-user tenant fallback** - tenant resolution in application-context middleware now falls back to forwarded user tenant context when available, improving consistency for app-to-app calls that carry user delegation.

### Changed

- **Application-context middleware mode handling** - `ResolveApplicationContext` now accepts variadic mode arguments and normalizes them, allowing combinations such as `tenant_required,user_required`.
- **Forwarded token error semantics** - when `user_required` is active, missing or invalid forwarded user tokens now return explicit `401` responses with focused error messages.
- **Feature coverage expanded** - middleware feature tests now include forwarded-user binding, forwarded-user tenant resolution, invalid forwarded token rejection, and combined mode behavior.

### Fixed

- **User creation payload consistency** - `UserController::store()` now forwards `password_confirmation` directly from the incoming request value, avoiding mismatches in downstream payload construction.

### Deprecated

- No changes.

### Security

- Forwarded user-token validation hardening in application middleware reduces the risk of accepting malformed delegated user context.

## [4.2.0] - 2026-05-20 "Bulwark"

### Added

- **Protected API scopes and token model** — introduced first-class protected API scope support with dedicated components: `ScopeApi`, `CaronteProtectedApiAccessToken`, `CaronteProtectedApiAccessContext`, `ConfiguredScopes`, and `ValidateProtectedApiAccessToken` / `ValidateProtectedApiScopes` middleware.
- **Protected API scope sync command** — added `caronte:protected-api:scopes:sync` for pushing configured protected API scopes to the Caronte server.
- **Operational handover documentation** — new `doc/client-handover.md` and `doc/admin-handover.md` guides for implementation and operations handoff.

### Changed

- **Configuration model for external API authorization** — canonical scope configuration is now under `protected_api.scopes`; permission-oriented naming remains available only as a deprecated compatibility path for migration windows.
- **Application/group token controls** — configuration now includes explicit TTL tuning for application and group JWT issuance windows.
- **Header and audience alignment** — service/API clients and middleware were updated to align request headers, audience handling, and alias registration with protected API scope semantics.
- **Documentation and tests refreshed** — deployment, routes, API, architecture, command, and test docs were updated alongside expanded feature coverage for protected API token and scope flows.

### Deprecated

- Legacy permission-oriented APIs and aliases remain temporarily available for migration compatibility, including permission naming in configuration and middleware/command compatibility shims. These are scheduled for removal in the next major release.

## [4.1.0] - 2026-05-14 "Northstar"

### Breaking Changes

- **Tenant key normalized to `tenant_id` in persistence and domain code** — local user and metadata persistence now use `tenant_id` as the canonical tenant field, replacing legacy `id_tenant` assumptions in package internals.
- **Composite primary keys enforced for tenant scoping** — `Users` now uses `['uri_user', 'tenant_id']` and `UsersMetadata` now uses `['uri_user', 'tenant_id', 'scope', 'key']`. Host-side custom queries or scripts that assumed `uri_user` alone was globally unique must be updated.

See `BREAKING_CHANGES.md` for migration guidance.

### Added

- **Tenant-aware composite key persistence model** — package models now use composite keys for tenant-safe reads/writes (`CaronteUser`, `CaronteUserMetadata`).
- **Migration backfill path for legacy rows** — migrations populate `tenant_id` from legacy `id_tenant` (Users table) or configured tenancy defaults (UsersMetadata table) when needed.

### Changed

- **Tenant key naming consistency** — core runtime flows (`Caronte`, `CaronteUserToken`, `CaronteUserHelper`, `ListUsers`) are aligned on `tenant_id`.
- **Local user sync hardening** — local `updateUserData()` now persists users and metadata using tenant-scoped composite identity.
- **Schema evolution behavior** — migration upgrade paths now enforce/repair expected primary keys for metadata without relying on deprecated DBAL-only APIs.

### Fixed

- **Cross-tenant metadata leakage risk reduced** — metadata lookup and user relationship queries now consistently apply tenant scoping with `tenant_id`.
- **Tenant-scoped user listing output consistency** — command output and API payload handling now consistently reference `tenant_id`.

## [3.6.0] - 2026-05-13 "Keystone"

### Added

- **Single-tenant tenancy mode** — new tenancy configuration supports `multi` and `single` operation modes through `CARONTE_TENANCY_MODE` and `CARONTE_TENANT_ID`.
- **`CaronteTenancy` support helper** — new `Ometra\Caronte\Support\CaronteTenancy` centralizes tenant mode resolution, configured tenant access, and runtime tenant validation.

### Changed

- **Tenant context enforcement in auth flow** — `AuthController`, `ResolveApplicationContext`, and `ValidateUserToken` now enforce tenant consistency and return `403` when the active tenant context conflicts with token/application tenant context.
- **Tenant-aware downstream API requests** — `CaronteHttpClient` now forwards `X-Tenant-Id` when tenant context is available, keeping server-side authorization aligned with SDK tenant resolution.
- **Configuration and operational docs refreshed** — deployment and route documentation now include tenancy-mode guidance and tenant-context behavior.

### Fixed

- **Tenant mismatch hardening** — tenant mismatches that could previously pass through ambiguous context paths now fail fast with explicit forbidden responses.

## [3.5.0] - 2026-05-13 "Waypoint"

### Added

- **Pending tenant-selection login state** — web login now stores a short-lived pending login context when Caronte responds with `tenant_selection_required`, allowing the tenant-selection step to complete without re-entering credentials.

### Changed

- **Tenant selection UX (Blade + Inertia)** — login views now switch into tenant-selection mode by pre-filling and locking the email field, hiding password input, and requiring tenant selection when pending login context is available.
- **Auth API login payload support** — `AuthApi::login()` now accepts `tenant_selection_token` and forwards it to `api/auth/login` when present.
- **Conflict response coverage** — `AuthController` and `CaronteResponse` flow now consistently returns/propagates tenant-selection conflict details for both JSON and web interactions.

### Fixed

- **Second-step login retry friction** — users no longer need to resend password on the tenant-selection retry after a `409 tenant_selection_required` response.

## [3.4.0] - 2026-05-11

### Added

- **Tenant API client** — new `TenantApi` class with tenant listing and detail helpers:
    - `TenantApi::listTenants()`
    - `TenantApi::showTenant()`
- **Tenant management Artisan commands**:
    - `caronte:tenants:list`
    - `caronte:tenants:show`
- **Interactive admin menu integration** — `ManagementCaronte` now exposes tenant management commands from the interactive console flow.

### Changed

- **User listing command options** — `caronte:users:list` now supports `--app-users` as the explicit flag for app users. `--all` is retained as a deprecated alias for backwards compatibility.
- **Users table output** — tenant information is now included in command output.
- **Users API forwarding** — list command forwards the correct `app_users` parameter to the API client.
- **Test coverage updates** — `tests/Feature/CommandBehaviorTest.php` now covers tenant commands, tenant column output, and `--app-users` behavior.

## [3.3.1] - 2026-05-11

### Fixed

- **Users metadata migration compatibility across Laravel 10/11/12** — `database/migrations/user_metadata_table.php` now resolves primary key metadata without relying on Doctrine DBAL-only APIs. The migration first uses the schema builder `getIndexes()` API when available and falls back to native MySQL/MariaDB `SHOW INDEX` introspection when needed, preventing failures on newer Laravel versions where deprecated Doctrine schema-manager methods are unavailable.

## [3.3.0] - 2026-05-07 "Chronos"

### Added

- **Configurable token clock skew** — `token_clock_skew_seconds` config key (default `60`) and `CARONTE_TOKEN_CLOCK_SKEW_SECONDS` env var. `CaronteUserToken::assertNotBefore` now uses the configured leeway when validating `iat`/`nbf` claims, tolerating small clock differences between hosts. Tests added to assert acceptance within the skew window and rejection beyond it.
- **GitHub Actions CI workflow** — `.github/workflows/ci.yml` runs two jobs on every push to `main`/`dev` and on pull requests:
    - **PHP job** — sets up PHP 8.4, validates Composer, installs dependencies, and runs PHPUnit via `composer test`.
    - **TypeScript job** — sets up Node 24 with npm cache, installs dependencies via `npm ci`, and runs `tsc --noEmit`.
- **Frontend TypeScript migration** — React pages migrated from `.jsx` to `.tsx`; shared `resources/js/types.ts` provides typed interfaces for SDK data structures (`CaronteUser`, `CaronteRole`, `CaronteMetadata`, etc.). `tsconfig.json` and `package.json` (TypeScript dev-dependencies) added.
- **Legacy management routes & JSON endpoints** — `users.list` and `users.roles.list` named routes added for backwards-compatible integrations. `UserController` gains `list`, `listRoles`, and legacy `update`/`delete` wrappers; `RoleController` adds a redirect for unsupported legacy mutations.
- **Blade view improvements** — create-user modal now includes a temporary password field; `roles-checkboxes.blade.php` partial made robust with an `availableRoles` fallback variable.

### Changed

- `ManagementController` now passes configured roles to index/dashboard views so Blade and SPA components can render role pickers without an extra API call.
- Documentation updated: `doc/deployment-instructions.md` notes TSX assets; `doc/routes-documentation.md` describes current vs legacy routes.

## [3.2.1] - 2026-05-07

### Changed

- **`X-Application-Token` added to user requests** — `CaronteHttpClient::userRequest()` now includes the `X-Application-Token` header alongside `X-User-Token` on every user-authenticated API call. This ensures the Caronte server can verify both the calling application and the requesting user in a single round-trip, strengthening the trust chain for all user-context API operations.

## [3.2.0] - 2026-05-06 "Hermes"

### Added

- **Tenant selection flow** — multi-tenant users are now guided through a tenant picker at login time.
    - `AuthController` reads `tenant_options` from session and exposes them to the view/SPA.
    - `AuthApi::login()` accepts an optional `$tenantId` parameter and includes it in the sign-in payload.
    - 409 `tenant_selection_required` responses are handled gracefully: the controller returns a conflict response with tenant data and keeps the user on the login page.
    - `CaronteResponse::conflict()` — new helper for returning 409 conflict responses (JSON or redirect).
    - `CaronteResponse::redirectErrors()` — new helper that shapes session errors correctly before a redirect.
    - React login form (`resources/js/Pages/auth/login.jsx`) and Blade login view (`resources/views/auth/login.blade.php`) both render a tenant dropdown when `tenant_options` are provided.
    - New feature tests cover the tenant-selection redirect and tenant forwarding to the Caronte API.
- **Phase-2 (flat) JWT claim structure** — `CaronteUserToken::userPayload()` now reads top-level JWT claims (`sub`, `tenant_id`, `roles`, `metadata`, etc.) while keeping the legacy nested `user` claim as a fallback.
    - `Caronte::getUser()` updated to use the new `userPayload()` method.
    - Both claim structures are supported transparently; no host-application change is required for legacy tokens.
- **`ProvisioningApi`** — new API client for server-side tenant provisioning.
    - `ProvisioningApi::provisionTenant()` — triggers tenant provisioning on the Caronte server.
    - `AuthApi` updated to use the new two-factor endpoints and to include the user token when calling logout.
- **`.editorconfig`** — enforces UTF-8 encoding, LF line endings, 4-space indentation (2 spaces for YAML/JSON), and trailing-newline rules across the repository.
- **`.gitattributes`** — enables consistent text normalization, sets diff drivers for common file types, and marks binary formats correctly.

### Changed

- **Request-based JSON/web detection** — `RouteMode` static helper replaced with direct `Request`-based detection inline in `CaronteResponse`, `AuthController`, `ValidateUserToken`, and related middleware. Removes tight coupling to a static singleton and makes the detection testable.
- `CaronteUserHelper` metadata lookup now uses the `DB` facade directly instead of going through the model layer, improving consistency under multi-tenant DB contexts.
- Routes prefix and login URL config defaults updated to reflect new management/Inertia conventions.
- Documentation suite (`doc/`) updated across multiple files:
    - `api-documentation.md` — documents `ProvisioningApi`, phase-2 claim parsing, and updated `AuthApi` endpoints.
    - `business-logic-and-core-processes.md` — covers tenant-selection flow and phase-2 JWT handling.
    - `deployment-instructions.md` — updated deployment notes.
    - `routes-documentation.md` — reflects route prefix changes.
    - `tests-documentation.md` — updated for new tests (tenant selection, provisioning, open-questions suite).
- `README.md` — minor copy cleanup (removed stray BOM character from header).

### Removed

- **`Support/RouteMode.php`** — the `Ometra\Caronte\Support\RouteMode` class has been removed. Its functionality is now handled inline via `Request` methods. See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for migration guidance.

## [3.1.0] - 2026-05-04 "Aegis"

### Added

- **OpenID Connect (OIDC) authentication** — full OIDC/OAuth 2.0 authorization code flow with PKCE support.
    - `OidcAuthController` — handles authorization redirect, callback/token exchange, and logout.
    - `Oidc/OidcClient` — authorization URL construction, authorization-code exchange, and token refresh.
    - `Oidc/OidcTokenValidator` — validates OIDC ID tokens against a JWKS endpoint.
    - `Oidc/JwksCache` — fetches and caches the Caronte server JWKS; TTL configurable via `CARONTE_OIDC_JWKS_CACHE_TTL`.
    - `Oidc/Jwk` — parses a JWK entry and verifies RS256 signatures.
    - `Oidc/Pkce` — generates PKCE `code_verifier` and S256 `code_challenge` pairs.
    - `Oidc/Base64Url` — URL-safe Base64 encode/decode helper.
    - `CaronteUserToken` now selects the correct validator (`legacy`, `oidc`, or `dual`) based on `config('caronte.auth_mode')` and the token `kid` header.
    - `Caronte::getUser()` extended to build a user object from standard OIDC claims (`sub`, `name`, `email`, `email_verified`) when the legacy `user` claim is absent.
    - `AuthController` redirects to the OIDC authorization endpoint when `auth_mode` is `oidc` or `dual`.
    - New routes registered under `caronte/oidc/*` for the OIDC flow.
- **Application-group tokens** — group-level server-to-server authentication.
    - `CaronteApplicationAccessToken` — generates and validates application-group tokens using `CARONTE_APPLICATION_GROUP_ID` + `CARONTE_APPLICATION_GROUP_SECRET`.
    - `CaronteApplicationAccessContext` — HTTP context object bound into the container on successful group-token validation.
    - `ValidateApplicationAccessToken` middleware (`caronte.app-token` alias) — validates the `X-Application-Access-Token` header and binds `CaronteApplicationAccessContext`.
    - `ValidateApplicationAccessPermissions` middleware (`caronte.app-permissions` alias) — asserts that the bound `CaronteApplicationAccessContext` carries the required permissions.
    - `CaronteApplicationToken` updated to match and emit group context.
    - `CaronteUserToken` updated to support group-signed tokens.
    - `ResolveApplicationContext` updated to populate group context when present.
- **Permission synchronisation** — declarative permission management.
    - `PermissionApi` — API client for Caronte permission endpoints.
    - `ConfiguredPermissions` helper — encapsulates `config('caronte.permissions')` logic.
    - `caronte:permissions:sync [--dry-run]` Artisan command — syncs configured permissions to the Caronte server.
- New config keys in `config/caronte.php`:
    - `application_group_id` / `application_group_secret` — group credentials (from `CARONTE_APPLICATION_GROUP_ID` / `CARONTE_APPLICATION_GROUP_SECRET`).
    - `auth_mode` — `legacy` (default), `oidc`, or `dual`; controls which token validator is used.
    - `oidc.issuer`, `oidc.client_id`, `oidc.client_secret`, `oidc.redirect_uri`, `oidc.scopes`, `oidc.jwks_cache_ttl` — full OIDC client configuration.
- New Feature tests: group-token validation, `ValidateApplicationAccessToken` and `ValidateApplicationAccessPermissions` middleware behaviour, `caronte:permissions:sync` command.
- `.env.example` updated with `CARONTE_APPLICATION_GROUP_ID` and `CARONTE_APPLICATION_GROUP_SECRET`.

### Changed

- `equidna/bee-hive` constraint bumped from `>=2.0` to `^3.0` — requires BeeHive 3.x; see dependency note below.
- `Caronte::syncUser()` now binds a `TenantContext` during local DB operations, ensuring tenant-scoped writes are consistent with the active BeeHive context.
- Documentation suite (`doc/`) comprehensively updated:
    - `api-documentation.md` — covers `CaronteApiClient`, all API clients, application credentials (individual and group tokens), incoming middleware, and context objects.
    - `artisan-commands.md` — adds `caronte:permissions:sync` documentation.
    - `business-logic-and-core-processes.md` — updated for application-token flows and permission synchronisation.
    - `routes-documentation.md` — covers new OIDC routes and application-token middleware routes.
    - `tests-documentation.md` — reflects new test helpers and group-token/middleware test coverage.
- `README.md` updated with a Token Types reference section and OIDC quick-start.

### Dependency Note

`equidna/bee-hive` is now required at `^3.0`. If your host application pins BeeHive at `^2.x`, you must upgrade BeeHive before upgrading to Caronte SDK `^3.1`. No other public APIs were changed or removed.

## [3.0.0] - 2026-04-28 "Archon"

### Breaking Changes

See `BREAKING_CHANGES.md` for full migration guidance.

- `CARONTE_APP_ID` environment variable renamed to `CARONTE_APP_CN` — update `.env` and all deployment configs.
- Config keys normalised to `lower_snake_case`: `app_id` → `app_cn`, `ISSUER_ID` → `issuer_id` — update any code that reads `config('caronte.*')` directly.
- `CaronteToken` class renamed to `CaronteUserToken` — update all imports and type hints.
- `ServiceClient` class renamed to `CaronteServiceClient` — update all imports.
- `CaronteHttpClient` namespace moved from `Ometra\Caronte\Api` to `Ometra\Caronte\Support` — update imports.
- `ApplicationToken` renamed to `CaronteApplicationToken` and moved to `Ometra\Caronte\Support` — update imports.
- Middleware `ValidateSession` renamed to `ValidateUserToken` — update `$middlewareAliases` and route groups.
- Middleware `ValidateRoles` renamed to `ValidateUserRoles` — update `$middlewareAliases` and route groups.
- Middleware `ResolveApplicationToken` and `ResolveTenantContext` removed — replace with `ResolveApplicationContext`.
- `CaronteRequest` class removed — use `CaronteHttpClient` (`Support` namespace) directly.
- `CaronteRoleManager` class removed — use `RoleApi` directly.
- `ManagementCaronte` command removed — the `caronte:admin` TUI menu remains as the interactive entry point.
- `GuardsManagement` console concern removed — use `BindsTenantContext` for commands needing tenant context.
- Support classes `RequestContext` and `TenantContextResolver` removed — functionality absorbed into `ResolveApplicationContext`.
- `BaseApiClient` removed — extend `CaronteApiClient` instead.

### Added

- `AuthApi` — dedicated API class encapsulating all authentication-related Caronte server calls.
- `CaronteApiClient` — base API client for Caronte server communication, replacing the fragmented `BaseApiClient`/`BaseHttpClient` hierarchy.
- `CaronteServiceClient` — renamed and promoted service client extending `CaronteHttpClient`.
- `BindsTenantContext` console concern — used by commands that require an active tenant context.
- `ResolveApplicationContext` middleware — unified middleware replacing the previous `ResolveApplicationToken` + `ResolveTenantContext` pair.
- `RouteMode` support class — enum-like value object controlling route registration modes.
- `resources/views/layouts/base.blade.php` — new default base layout shipping comprehensive UI CSS: color palette, typography, form and button styles, responsive container.
- Default `$branding` variable injected into all auth and management views — allows host apps to customise the UI without publishing views.
- Configurable notification senders via `config('caronte.notifications')`: `two_factor_sender` and `password_recovery_sender` can now be overridden per-app.
- `CARONTE_UI_*` environment variables for branding customisation: `CARONTE_UI_APP_NAME`, `CARONTE_UI_HEADLINE`, `CARONTE_UI_SUBHEADLINE`, `CARONTE_UI_SUPPORT_EMAIL`, `CARONTE_UI_LOGO_URL`, `CARONTE_UI_ACCENT`.
- Flash/messages partial (`partials/messages.blade.php`) — full rewrite with error deduplication and unified flash+validation error rendering.
- `_gen_docs.py` — documentation generation script for maintaining the `doc/` suite.
- Feature tests: view rendering without explicit branding, mailables with string expiration values, flash partial deduplication.

### Changed

- `equidna/bee-hive` constraint relaxed from `^2.0` to `>=2.0` — allows projects on future minor/patch BeeHive releases without requiring a Caronte bump.
- Version field removed from `composer.json` — version authority delegated entirely to Git tags.
- `AuthController` significantly refactored for clarity and alignment with `AuthApi`.
- `CaronteServiceProvider` updated to register `CaronteApiClient` singleton, new middleware aliases, and `BindsTenantContext` concern.
- All Artisan command classes updated to use `BindsTenantContext` in place of `GuardsManagement`.
- Documentation suite (`doc/`) fully regenerated with `_gen_docs.py`; all guides updated to reflect v3 class names, middleware names, and config keys.
- `README.md` restructured for clearer quick-start, configuration reference, middleware reference, and architecture summary.

### Removed

- `CaronteRequest` legacy HTTP wrapper.
- `CaronteRoleManager` orchestrator class (replaced by direct `RoleApi` usage).
- `ManagementCaronte` interactive command class (the `caronte:admin` command is retained but directly implemented).
- `GuardsManagement` console concern.
- `RequestContext` support class.
- `TenantContextResolver` support class.
- `BaseApiClient` abstract class.
- `ResolveApplicationToken` middleware.
- `ResolveTenantContext` middleware.
- `RELEASE_NOTES.md` from version control (generated fresh per release).

## [2.1.0] - 2026-04-27 "Sentinel"

### Added

- `CARONTE_TLS_VERIFY` configuration option: TLS certificate verification can now be toggled independently from `CARONTE_ALLOW_HTTP_REQUESTS`.
- `caronte.application` middleware alias (`ResolveApplicationContext`): validates `X-Application-Token` header for server-to-server routes, binds `CaronteApplicationContext`, and supports `tenant_required` mode.
- `CaronteHttpClient` — dedicated HTTP client wrapping auth and application requests with proper headers.
- `ApplicationToken` support class for generating and matching application tokens (`base64(sha1(APP_ID) + ":" + APP_SECRET)`).
- `CaronteResponse` support class standardising the API response envelope shape.
- `ConfiguredRoles` support class encapsulating `config('caronte.roles')` logic; always injects `root`.
- `RequestContext` support class for per-request data propagation.
- `TenantContextResolver` support class with three-step fallback chain: route/request parameter → BeeHive context → JWT `id_tenant` claim.
- `CaronteApplicationContext` HTTP context class bound into the container on successful application-token validation.
- `CaronteTenantResolver` tenancy resolver integrating with `equidna/bee-hive`.
- `CaronteApiException` exception for non-2xx responses from the Caronte server.
- `GuardsManagement` console concern enforcing `caronte.management.enabled` guard across all management commands.
- `SendsPasswordRecovery` and `SendsTwoFactorChallenge` contracts abstracting notification delivery.
- `PasswordRecoveryMail` and `TwoFactorChallengeMail` mailables for host-side email delivery (`CARONTE_NOTIFICATION_DELIVERY=host`).
- `LaravelPasswordRecoverySender` and `LaravelTwoFactorChallengeSender` notification sender implementations.
- New Artisan commands (replacing the legacy monolithic command set):
    - `caronte:admin` — interactive TUI menu.
    - `caronte:roles:sync [--dry-run]` — syncs configured roles to the Caronte server.
    - `caronte:users:list [--tenant=] [--search=] [--all]` — lists users.
    - `caronte:users:create [--tenant=] [--name=] [--email=] [--password=] [--role=]*` — creates a user.
    - `caronte:users:update [--tenant=] [--uri-user=] [--name=]` — updates a user.
    - `caronte:users:delete [--tenant=] [--uri-user=]` — deletes a user.
    - `caronte:users:roles:sync [--tenant=] [--uri-user=] [--role=]*` — syncs roles for a user.
- Management UI user-detail view (`management/user-detail`) for both Blade and Inertia rendering.
- `users_table` migration: added `id_tenant` column for BeeHive tenant association.
- Comprehensive documentation suite in `doc/`:
    - `deployment-instructions.md`
    - `api-documentation.md`
    - `routes-documentation.md`
    - `artisan-commands.md`
    - `tests-documentation.md`
    - `architecture-diagrams.md` (C4, container, component, and sequence diagrams)
    - `monitoring.md`
    - `business-logic-and-core-processes.md`
    - `open-questions-and-assumptions.md`
- 9 focused Feature tests replacing the previous smoke-test suite:
    - `AuthContractTest`, `CommandBehaviorTest`, `CommandBehaviorWhenManagementDisabledTest`, `ConfigurationValidationTest`, `ManagementUiTest`, `ManagementUiWhenDisabledTest`, `MiddlewareBehaviorTest`, `RouteRegistrationTest`, `RouteRegistrationWhenDisabledTest`.
- `.env.example` updated with all supported environment variables.

### Changed

- **Default issuer validation is now `true`**: `CARONTE_ENFORCE_ISSUER` defaults to `true`; JWT issuer claim is validated on every request by default. See migration notes in `BREAKING_CHANGES.md`.
- `routes/web.php` restructured: explicit HTTP-verb groupings, management routes use a dedicated closure group.
- `CaronteToken` refactored: token exchange and signature checks isolated; static `$exchanging` guard prevents recursive exchange.
- `CaronteRequest` refactored: all methods delegate to `CaronteHttpClient`.
- `CaronteRoleManager` refactored: uses `ConfiguredRoles` and `RoleApi`; `previewSync()` and `syncConfiguredRoles()` are the canonical entry points.
- `CaronteServiceProvider` refactored: middleware registration extracted; commands registered conditionally on console.
- `PermissionHelper` refactored: `hasApplication()` and `hasRoles()` both use `ApplicationToken::matches()` and `ConfiguredRoles` internally.
- `ValidateSession` updated: forwards renewed JWT in `X-User-Token` response header for SPA/API clients.
- All four controllers (`AuthController`, `ManagementController`, `UserController`, `RoleController`) refactored for domain separation and consistency.
- `ClientApi` and `RoleApi` refactored to use `CaronteHttpClient`.
- CSS, Blade views, and Inertia JSX pages overhauled for visual and structural consistency.
- `ManagementController@dashboard()` adds pagination.
- `README.md` overhauled: installation, configuration, middleware reference, command reference, and architecture overview.
- `logoutAll` behaviour clarified: revokes sessions only for the current Caronte application, not globally across all apps.

### Removed

- `CaronteCommand` base class (replaced by `GuardsManagement` concern).
- Monolithic `ManagementRoles` and `ManagementUsers` command classes.
- Individual legacy commands: `CreateRole`, `DeleteRole`, `ShowRoles`, `UpdateRole`, `AttachRoles`, `DeleteRolesUser`, `ShowRolesByUser`.
- Legacy smoke tests: `RoutesSmokeTest`, `PublishCommandsTest`.

### Security

- JWT issuer claim validation is now **on by default**, eliminating a class of token-forgery risk in installations that did not explicitly configure issuer verification.

## [2.0.0] - 2026-04-13

### Breaking Changes

- Renamed API method `Caronte::getTenant()` to `Caronte::getTenantId()`.
- Changed tenant access contract from tenant object payload to `id_tenant` string return value.
- Removed fallback tenant payload (`id_tenant: 0`, `name: "No tenant"`) when tenant information is missing.

### Changed

- `Caronte::getTenantId()` now resolves tenant information from the `user` claim and enforces `id_tenant` presence.
- Simplified `getTenantId()` implementation by removing redundant catch/rethrow blocks.
- Updated facade annotations and README usage examples to use `getTenantId()`.

### Fixed

- Improved tenant retrieval error handling by introducing `TenantMissingException` when `id_tenant` is missing.
- Replaced deprecated `str_contains` usage in token validation message detection with `stripos(... ) !== false` for compatibility.

### Removed

- Removed `PUBLISHING.md` from repository documentation set.

## [1.6.0] - 2026-03-23

### Added

- Added `Caronte::getTenant()` to expose the `tenant` JWT claim through the main client and facade.
- Added a default fallback tenant payload when the claim is missing, returning `id_tenant: 0` and `name: "No tenant"`.

## [1.5.0] - 2026-03-08

### Fixed

- Fixed provider boot validation to avoid failing unrelated console tooling commands when `CARONTE_*` variables are not initialized yet.
- Fixed publishing documentation env key typo: `CARONTE_ENFORCER_ISSUER` -> `CARONTE_ENFORCE_ISSUER`.

### Breaking Changes

- Removed legacy controller `src/Http/Controllers/CaronteController.php`.
- Removed legacy API wrapper `src/AppBoundRequest.php`.
- Removed legacy route file `src/routes/web.php`.
- Removed legacy package config duplicate `src/config/caronte.php`.
- Removed obsolete sync job `src/Jobs/SynchronizeRoles.php`.
- Removed deprecated legacy views under `resources/views/auth/Management/`.

**Migration Path**:

- Use `routes/web.php` as the single source for package routes.
- Use `AuthController`, `ManagementController`, `UserController`, and `RoleController` under `src/Http/Controllers/`.
- Use `ClientApi` and `RoleApi` under `src/Api/`.

### Added

- Added package smoke tests with Testbench:
    - `tests/Feature/RoutesSmokeTest.php`
    - `tests/Feature/PublishCommandsTest.php` (8 tests validating publish command infrastructure)
    - `tests/TestCase.php`
    - `phpunit.xml.dist`
- Added `UserController::store()` as REST alias forwarding to `create()` for route compatibility.
- **Test Coverage**: 11 tests with 62 assertions ensure routes are properly registered and publish commands are configured correctly.

## [1.4.0] - 2026-02-08

### Breaking Changes

#### Controller Methods Renamed (REST Conventions)

- `RoleController::listAll()` → `index()`
- `RoleController::assign()` → `attach()`
- `UserController::list()` → `index()`
- `UserController::create()` → `store()`
- `ManagementController::dashboardApp()` → `dashboard()`
- `ManagementController::synchronizeData()` → `synchronize()`

**Migration Path**: Update route references and controller calls to use new method names.

#### Console Command Signatures Renamed

- `caronte-client:attached-roles` → `caronte-client:attach-roles`
- `caronte-client:edit-role` → `caronte-client:update-role`
- `caronte-client:users-roles` → `caronte-client:show-user-roles`
- `caronte-client:delete-roles-user` → `caronte-client:delete-user-roles`

**Migration Path**: Update any scripts or documentation referencing old command names.

#### Removed Classes

- Removed `AppBound.php` (deprecated backward-compatibility alias)
- Removed `SuperCommand.php` (use `CaronteCommand` instead)

### Changed

#### Directory Structure

- Renamed `src/Console/Commands/CrudRoles/` → `Roles/`
- Renamed `src/Console/Commands/CrudUsers/` → `Users/`
- Updated all namespaces accordingly

#### Console Commands

- Renamed `AttachedRoles` class → `AttachRoles`
- Updated all command signatures for consistency
- Updated ServiceProvider imports

### Fixed

- Fixed `SynchronizeRoles.php`: Corrected undefined `RoleManager` → `CaronteRoleManager`
- Fixed `CaronteRequest::passwordRecoverTokenValidation()`: Added `InertiaResponse` to return type
- Fixed HTTP SSL verification: `RoleApiClient::makeRequest()` now honors `ALLOW_HTTP_REQUESTS` config
- Fixed `ManagementController::dashboard()`: Corrected return type to `View|InertiaResponse`

### Removed

- Removed unused imports across 6 files:
    - `AttachRoles.php`: `Illuminate\Support\Str`
    - `ManagementUsers.php`: `CaronteRoleManager`, `Illuminate\Support\Str`
    - `ManagementRoles.php`: `Illuminate\Support\Str`
    - `DeleteRolesUser.php`: `Illuminate\Support\Str`
    - `PermissionHelper.php`: `Equidna\Toolkit\Exceptions\UnauthorizedException`
- Removed unused config keys from `config/caronte.php`:
    - `queue_connection`
    - `queue_name`

### Documentation

- Updated README.md:
    - Corrected all console command names and signatures
    - Improved feature descriptions
    - Added installation instructions
    - Clarified user workflow requirements
    - Updated command tables with accurate names

## [1.3.4] - Previous Release

See git history for changes prior to 1.4.0.
