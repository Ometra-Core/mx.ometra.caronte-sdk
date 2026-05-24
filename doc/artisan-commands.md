# Artisan Commands

This package registers custom commands only when running in console.

## 1. Command Index

| Command                              | Purpose                                                  |
| ------------------------------------ | -------------------------------------------------------- |
| caronte:admin                        | Interactive command hub for common management tasks      |
| caronte:roles:sync                   | Sync configured roles from config/caronte.php to Caronte |
| caronte:protected-api:scopes:sync    | Sync protected API scopes to Caronte                     |
| caronte:permissions:sync             | Deprecated alias of scope sync                           |
| caronte:tenants:list                 | List tenants visible to current application              |
| caronte:tenants:show {tenant}        | Show tenant detail                                       |
| caronte:users:list                   | List users for tenant/app filters                        |
| caronte:users:create                 | Create user and sync role set                            |
| caronte:users:update {uri_user?}     | Update user display name                                 |
| caronte:users:delete {uri_user?}     | Delete user                                              |
| caronte:users:roles:sync {uri_user?} | Sync user roles                                          |

## 2. Detailed Commands

### caronte:admin

- Class: Ometra\Caronte\Console\Commands\ManagementCaronte
- Interactive menu that calls other commands.
- Use when operators prefer guided CLI flow.

### caronte:roles:sync

- Class: Ometra\Caronte\Console\Commands\Roles\SyncRoles
- Signature options:
    - --dry-run: preview normalized roles and remote status without PUT sync
- Reads role definitions from config(caronte.roles).

Example:

- php artisan caronte:roles:sync --dry-run
- php artisan caronte:roles:sync

### caronte:protected-api:scopes:sync

- Class: Ometra\Caronte\Console\Commands\ProtectedApi\SyncScopes
- Signature options:
    - --dry-run: shows normalized scope list only
- Reads scopes from config(caronte.protected_api.scopes), falling back to deprecated config(caronte.permissions).

### caronte:permissions:sync

- Class: Ometra\Caronte\Console\Commands\Permissions\SyncPermissions
- Deprecated compatibility command.
- Same behavior as protected-api scope sync.

### caronte:tenants:list

- Class: Ometra\Caronte\Console\Commands\Tenants\ListTenants
- Option:
    - --search=

### caronte:tenants:show

- Class: Ometra\Caronte\Console\Commands\Tenants\ShowTenant
- Argument:
    - tenant

### caronte:users:list

- Class: Ometra\Caronte\Console\Commands\Users\ListUsers
- Options:
    - --tenant=
    - --search=
    - --app-users
    - --all (deprecated alias)

### caronte:users:create

- Class: Ometra\Caronte\Console\Commands\Users\CreateUser
- Options:
    - --tenant=
    - --name=
    - --email=
    - --password=
    - --role=\* (configured role names)

### caronte:users:update

- Class: Ometra\Caronte\Console\Commands\Users\UpdateUser
- Arguments/options:
    - {uri_user?}
    - --tenant=
    - --name=

### caronte:users:delete

- Class: Ometra\Caronte\Console\Commands\Users\DeleteUser
- Arguments/options:
    - {uri_user?}
    - --tenant=
    - --force

### caronte:users:roles:sync

- Class: Ometra\Caronte\Console\Commands\Users\SyncUserRoles
- Arguments/options:
    - {uri_user?}
    - --tenant=
    - --role=\*
    - --clear

## 3. Scheduling

No command scheduling is defined by this package. Host applications may schedule any command if needed.

## 4. Operational Notes

- Most commands rely on Caronte server connectivity.
- User and tenant commands require tenant context for tenant-scoped endpoints.
- Role/scope sync should be part of deployment or release workflows when config changes.
