# Routes Documentation

All routes are registered by `CaronteServiceProvider` inside the `web` middleware group. No API routes are provided.

---

## Route Configuration

| Config key                        | Default              | Effect                       |
| --------------------------------- | -------------------- | ---------------------------- |
| `caronte.routes_prefix`           | empty string         | Prefix for all auth routes   |
| `caronte.management.enabled`      | `true`               | Enables management routes    |
| `caronte.management.route_prefix` | `caronte/management` | Prefix for management routes |

---

## Authentication Routes

These routes are always registered (cannot be disabled).

| Method   | URI                                  | Name                                      | Middleware | Description                                                             |
| -------- | ------------------------------------ | ----------------------------------------- | ---------- | ----------------------------------------------------------------------- |
| GET      | `/{prefix}/login`                    | `caronte.login.form`                      | `web`      | Show login form                                                         |
| POST     | `/{prefix}/login`                    | `caronte.login`                           | `web`      | Submit credentials                                                      |
| GET/POST | `/{prefix}/logout`                   | `caronte.logout`                          | `web`      | Log out locally and revoke the server token via `POST /api/auth/logout` |
| GET      | `/{prefix}/oidc/login`               | `caronte.oidc.login`                      | `web`      | Redirect to OIDC login when `auth_mode=oidc`                            |
| GET      | `/{prefix}/oidc/callback`            | `caronte.oidc.callback`                   | `web`      | Consume OIDC callback                                                   |
| POST     | `/{prefix}/oidc/logout`              | `caronte.oidc.logout`                     | `web`      | OIDC logout                                                             |
| POST     | `/{prefix}/two-factor`               | `caronte.twoFactor.request`               | `web`      | Request 2FA challenge                                                   |
| GET      | `/{prefix}/two-factor/{token}`       | `caronte.twoFactor.login`                 | `web`      | Consume 2FA token                                                       |
| GET      | `/{prefix}/password/recover`         | `caronte.password.recover.form`           | `web`      | Show recovery form                                                      |
| POST     | `/{prefix}/password/recover`         | `caronte.password.recover.request`        | `web`      | Request recovery link                                                   |
| GET      | `/{prefix}/password/recover/{token}` | `caronte.password.recover.validate-token` | `web`      | Show new-password form after token validation                           |
| POST     | `/{prefix}/password/recover/{token}` | `caronte.password.recover.submit`         | `web`      | Submit new password                                                     |

Default `{prefix}`: empty string. With default config the login route is `/login`, logout is `/logout`, and 2FA is `/two-factor`.

---

## Management Routes

Registered only when `caronte.management.enabled = true`.

All management routes require:

- `caronte.session` (ValidateUserToken)
- `caronte.roles:{access_roles}` (ValidateUserRoles)

### Current Routes

Use these names in new code.

| Method | URI                                   | Name                                       | Description               |
| ------ | ------------------------------------- | ------------------------------------------ | ------------------------- |
| GET    | `/{mgmt_prefix}`                      | `caronte.management.dashboard`             | Management dashboard      |
| POST   | `/{mgmt_prefix}/roles/sync`           | `caronte.management.roles.sync`            | Sync roles to Caronte     |
| POST   | `/{mgmt_prefix}/users`                | `caronte.management.users.store`           | Create a user             |
| GET    | `/{mgmt_prefix}/users/{uri}`          | `caronte.management.users.show`            | Show user detail          |
| PUT    | `/{mgmt_prefix}/users/{uri}`          | `caronte.management.users.update.direct`   | Update user data          |
| DELETE | `/{mgmt_prefix}/users/{uri}`          | `caronte.management.users.delete.direct`   | Delete user               |
| GET    | `/{mgmt_prefix}/users/{uri}/roles`    | `caronte.management.users.roles.list`      | Return user roles as JSON |
| PUT    | `/{mgmt_prefix}/users/{uri}/roles`    | `caronte.management.users.roles.sync`      | Sync user roles           |
| POST   | `/{mgmt_prefix}/users/{uri}/metadata` | `caronte.management.users.metadata.store`  | Store user metadata       |
| DELETE | `/{mgmt_prefix}/users/{uri}/metadata` | `caronte.management.users.metadata.delete` | Delete metadata key       |

### Legacy Compatibility Routes

These routes are kept for already-published Blade management views and older host integrations. Do not use them for new code unless you are maintaining a published legacy view.

| Method | URI                           | Name                              | Description                                                                  |
| ------ | ----------------------------- | --------------------------------- | ---------------------------------------------------------------------------- |
| GET    | `/{mgmt_prefix}/users/list`   | `caronte.management.users.list`   | Legacy JSON user search endpoint                                             |
| POST   | `/{mgmt_prefix}/users/update` | `caronte.management.users.update` | Legacy form endpoint; expects `uri_user` in the request body                 |
| POST   | `/{mgmt_prefix}/users/delete` | `caronte.management.users.delete` | Legacy form endpoint; expects `uri_user` in the request body                 |
| POST   | `/{mgmt_prefix}/roles/create` | `caronte.management.roles.create` | Legacy route name; redirects with a warning because roles are config-managed |
| POST   | `/{mgmt_prefix}/roles/update` | `caronte.management.roles.update` | Legacy route name; redirects with a warning because roles are config-managed |
| POST   | `/{mgmt_prefix}/roles/delete` | `caronte.management.roles.delete` | Legacy route name; redirects with a warning because roles are config-managed |

Default `{mgmt_prefix}`: `caronte/management`

---

## Notes

- The `root` role is always treated as an access role regardless of `access_roles` configuration.
- The management UI can render either Blade views or Inertia pages depending on `management.use_inertia`.
- Views are loaded from `resources/views/vendor/caronte` if published, otherwise from the package.
- If a host app has older published Blade views, legacy compatibility routes prevent `RouteNotFoundException` while the views are migrated or republished.
- JSON vs redirect response mode is inferred from request expectations (`expectsJson`, `wantsJson`) and `api/*` path checks.

## Middleware Aliases

The package also registers middleware aliases that can be used on host app routes:

| Alias                                  | Purpose                                                       |
| -------------------------------------- | ------------------------------------------------------------- |
| `caronte.session`                      | Validate user JWT from session or bearer token                |
| `caronte.roles:<role>`                 | Require user role; `root` is accepted                         |
| `caronte.application`                  | Validate incoming app-to-app JWT headers                      |
| `caronte.application:tenant_required`  | Same as above, but requires `X-Tenant-Id`                     |
| `caronte.protected-api-token`          | Validate a Protected API Access Token from `Authorization`    |
| `caronte.protected-api-scopes:<scope>` | Require a protected API scope                                 |
| `caronte.app-token`                    | Deprecated alias for `caronte.protected-api-token`            |
| `caronte.app-permissions:<permission>` | Deprecated alias for `caronte.protected-api-scopes:<scope>`   |
