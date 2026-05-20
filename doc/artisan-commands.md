# Artisan Commands

All commands are prefixed with `caronte:` and registered by `CaronteServiceProvider`.

---

## caronte:admin

**Class:** `ManagementCaronte`

Interactive TUI menu that dispatches the sub-commands below. Useful for one-off administrative tasks.

```bash
php artisan caronte:admin
```

No arguments. Presents a selection menu; choose an action and follow the prompts.

---

## caronte:roles:sync

**Class:** `Roles\SyncRoles`

Reads roles defined in `config/caronte.php` under the `roles` key, displays their current remote status, and pushes them to the Caronte server.

```bash
# Preview (no remote changes)
php artisan caronte:roles:sync --dry-run

# Apply
php artisan caronte:roles:sync
```

**Options:**

| Option      | Description                                               |
| ----------- | --------------------------------------------------------- |
| `--dry-run` | Show table of configured vs. remote roles without pushing |

**Table columns:** Role name · Description · `uri_applicationRole` (SHA1) · Remote status (`ok` / `outdated` / `missing`)

---

## caronte:protected-api:scopes:sync

**Class:** `ProtectedApi\SyncScopes` (target name)

Reads protected API scopes defined in `config/caronte.php` under `protected_api.scopes` and pushes them to the Caronte server. Caronte server/admin uses these scopes when issuing Protected API Access Tokens for external clients that consume this host application's API.

```bash
# Preview normalized scopes
php artisan caronte:protected-api:scopes:sync --dry-run

# Apply
php artisan caronte:protected-api:scopes:sync
```

Example config:

```php
'protected_api' => [
    'scopes' => [
        'invoices.read' => 'Read invoices',
        'invoices.write' => 'Write invoices',
    ],
],
```

**Options:**

| Option      | Description                           |
| ----------- | ------------------------------------- |
| `--dry-run` | Show normalized scopes without pushing |

### Deprecated compatibility command

`caronte:permissions:sync` remains available only as a compatibility alias during this migration window. Do not use it for new integrations. It must be removed in the next major version together with the `permissions` payload/claim terminology.

---

## caronte:users:list

**Class:** `Users\ListUsers`

Lists all users registered in the Caronte server for a given tenant.

```bash
php artisan caronte:users:list
php artisan caronte:users:list --tenant=acme
```

**Options:**

| Option        | Description                                             |
| ------------- | ------------------------------------------------------- |
| `--tenant=`   | Tenant identifier required by user-scoped endpoints     |
| `--search=`   | Name or email filter                                    |
| `--app-users` | Only include users currently linked to this application |
| `--all`       | Deprecated compatibility alias                          |

---

## caronte:users:create

**Class:** `Users\CreateUser`

Creates a new user on the Caronte server.

```bash
php artisan caronte:users:create
php artisan caronte:users:create --name="Jane Doe" --email=jane@example.com --tenant=acme --role=admin --role=editor
```

**Options:**

| Option        | Description                    |
| ------------- | ------------------------------ |
| `--tenant=`   | Tenant identifier              |
| `--name=`     | Full name                      |
| `--email=`    | Email address                  |
| `--password=` | Initial password               |
| `--role=*`    | Role(s) to assign (repeatable) |

All options are prompted interactively if omitted.

---

## caronte:users:update

**Class:** `Users\UpdateUser`

Updates an existing user's data on the Caronte server.

```bash
php artisan caronte:users:update {uri_user?} --tenant=<tenant_id> --name="Updated Name"
```

**Arguments and options:**

| Input       | Description                                         |
| ----------- | --------------------------------------------------- |
| `uri_user`  | Optional argument; prompted when omitted            |
| `--tenant=` | Tenant identifier required by user-scoped endpoints |
| `--name=`   | Updated display name                                |

---

## caronte:users:delete

**Class:** `Users\DeleteUser`

Deletes a user from the Caronte server.

```bash
php artisan caronte:users:delete {uri_user?} --tenant=<tenant_id> [--force]
```

**Options:**

| Option      | Description                 |
| ----------- | --------------------------- |
| `--tenant=` | Tenant identifier           |
| `--force`   | Delete without confirmation |

Prompts for confirmation before deleting.

---

## caronte:users:roles:sync

**Class:** `Users\SyncUserRoles`

Overwrites the roles assigned to a user on the Caronte server.

```bash
# Assign roles
php artisan caronte:users:roles:sync {uri_user?} --role=admin --role=editor --tenant=acme

# Clear all roles
php artisan caronte:users:roles:sync {uri_user?} --clear --tenant=acme
```

**Options:**

| Option      | Description                    |
| ----------- | ------------------------------ |
| `--tenant=` | Tenant identifier              |
| `--role=*`  | Role(s) to assign (repeatable) |
| `--clear`   | Remove all roles from the user |

## caronte:tenants:list

**Class:** `Tenants\ListTenants`

Lists tenants visible to the configured application.

```bash
php artisan caronte:tenants:list --search=acme
```

**Options:**

| Option      | Description                                                  |
| ----------- | ------------------------------------------------------------ |
| `--search=` | Optional tenant id, external id, name, or description filter |

## caronte:tenants:show

**Class:** `Tenants\ShowTenant`

Shows a single tenant.

```bash
php artisan caronte:tenants:show <tenant_id>
```

---

## Interactive Prompts

All commands use `laravel/prompts` for interactive input. Skipping an option launches the interactive version; providing all options runs non-interactively (suitable for scripts/CI).
