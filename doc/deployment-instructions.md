# Deployment Instructions

## Prerequisites

- PHP `^8.2`
- Laravel `^12.0`
- A running Caronte authentication server (external)
- Database connection configured in the host application
- Composer

---

## 1. Installation

```bash
composer require ometra/caronte-sdk
```

The package auto-registers via Laravel's package discovery (`extra.laravel.providers`). No manual service-provider registration is required.

---

## 2. Configuration

### 2.1 Publish the config file

```bash
php artisan vendor:publish --tag=caronte:config
```

This creates `config/caronte.php` in the host application.

### 2.2 Required environment variables

At minimum, these values must be configured in `.env`:

```env
CARONTE_URL=https://your-caronte-server.example.com
CARONTE_APP_CN=your-app-canonical-name
CARONTE_APP_SECRET=a-strong-secret-at-least-32-chars
```

| Variable                           | Required                | Description                                                |
| ---------------------------------- | ----------------------- | ---------------------------------------------------------- |
| `CARONTE_URL`                      | Yes                     | Base URL of the Caronte authentication server              |
| `CARONTE_APP_CN`                   | Yes                     | Canonical name that identifies this application in Caronte |
| `CARONTE_APP_SECRET`               | Yes                     | Signing secret for short-lived application JWTs            |
| `CARONTE_ISSUER_ID`                | No                      | Overrides JWT issuer claim validation                      |
| `CARONTE_APPLICATION_GROUP_ID`     | No                      | Application group id for grouped user/app tokens           |
| `CARONTE_APPLICATION_GROUP_SECRET` | No                      | Signing secret for short-lived group JWTs                  |
| `CARONTE_APPLICATION_TOKEN_TTL_SECONDS` | No                 | TTL for `X-Application-Token` JWTs, default `300`          |
| `CARONTE_APPLICATION_GROUP_TOKEN_TTL_SECONDS` | No           | TTL for `X-Group-Token` JWTs, default `300`                |
| `CARONTE_AUTH_MODE`                | No                      | `legacy`, `oidc`, or `dual`                                |
| `CARONTE_TENANCY_MODE`             | No                      | `multi` (default) or `single`                              |
| `CARONTE_TENANT_ID`                | Required in single mode | Tenant id enforced when `CARONTE_TENANCY_MODE=single`      |

Most options have defaults in `config/caronte.php`. Use environment variables for deployment-specific values such as OIDC mode, TLS policy, management exposure, and notification delivery.

### 2.3 Key configuration options (excerpt)

```php
return [
    'use_2fa'               => false,        // Enable two-factor authentication
    'allow_http_requests'   => false,        // Allow non-TLS (dev only)
    'tls_verify'            => true,         // Verify TLS certificates
    'update_local_user'     => false,        // Sync user to local DB after token validation

    // 'server': Caronte sends; 'host': this app sends via Mailable classes
    'notification_delivery' => 'server',

    'routes_prefix' => 'caronte',
    'success_url'   => '/',
    'login_url'     => '/login',

    'http' => [
        'timeout'     => 10,   // seconds
        'retries'     => 1,
        'retry_sleep' => 150,  // ms between retries
    ],

    'roles' => [
        'admin'  => 'Administrator',
        'editor' => 'Content editor',
    ],

    'protected_api' => [
        'scopes' => [
            'invoices.read' => 'Read invoices through this application API',
        ],
    ],

    'management' => [
        'enabled'      => true,
        'route_prefix' => 'caronte/management',
        'access_roles' => ['admin'],
        'use_inertia'  => false,
        'features'     => [
            'metadata'         => false,
            'profile_pictures' => false,
        ],
    ],

    'table_prefix' => '',   // Prefix for package DB tables
];
```

---

## 3. Database Migrations

| Migration file            | Table created          |
| ------------------------- | ---------------------- |
| `users_table.php`         | `{prefix}Users`        |
| `user_metadata_table.php` | `{prefix}UserMetadata` |

```bash
php artisan migrate
```

To publish migration files for customisation:

```bash
php artisan vendor:publish --tag=caronte:migrations
```

> Set `table_prefix` before running the first migration.

---

## 4. Publishing Assets

| Tag                  | Published to                     |
| -------------------- | -------------------------------- |
| `caronte:config`     | `config/caronte.php`             |
| `caronte:views`      | `resources/views/vendor/caronte` |
| `caronte:migrations` | `database/migrations`            |
| `caronte:inertia`    | `resources/js/vendor/caronte`    |
| `caronte-assets`     | `public/vendor/caronte`          |

```bash
# Publish everything at once
php artisan vendor:publish --tag=caronte
```

When `caronte.management.use_inertia=true`, publish `caronte:inertia` and compile the copied TSX components from the host application's frontend build. Blade mode does not require those Inertia assets.

---

## 5. Middleware

These aliases are registered automatically by `CaronteServiceProvider`:

| Alias                                    | Purpose                                                                  |
| ---------------------------------------- | ------------------------------------------------------------------------ |
| `caronte.session`                        | Validates and auto-renews the user JWT                                   |
| `caronte.roles`                          | Checks the user has the specified roles                                  |
| `caronte.application`                    | Validates incoming app-to-app auth JWT headers                           |
| `caronte.protected-api-token`            | Validates tenant-bound Protected API Access Tokens                       |
| `caronte.protected-api-scopes`           | Checks scopes on a validated Protected API Access Token                  |
| `caronte.app-token`                      | Deprecated alias for `caronte.protected-api-token`                       |
| `caronte.app-permissions`                | Deprecated alias for `caronte.protected-api-scopes`                      |

```php
// Auth guard
Route::middleware('caronte.session')->group(function () { ... });

// Auth + role check
Route::middleware(['caronte.session', 'caronte.roles:admin,editor'])->group(...);

// Service-to-service
Route::middleware('caronte.application')->group(function () { ... });

// Service-to-service with mandatory tenant header
Route::middleware('caronte.application:tenant_required')->group(function () { ... });

// External API consumers
Route::middleware([
    'caronte.protected-api-token',
    'caronte.protected-api-scopes:invoices.read',
])->group(function () { ... });
```

For exact headers and token responsibilities, see [Client Handover](client-handover.md).

---

## 6. Notification Setup (Host Delivery Mode)

When `notification_delivery = 'host'`, the package dispatches emails itself. The sender classes are swappable:

```php
// config/caronte.php
'two_factor_sender'        => \Ometra\Caronte\Notifications\LaravelTwoFactorChallengeSender::class,
'password_recovery_sender' => \Ometra\Caronte\Notifications\LaravelPasswordRecoverySender::class,
```

Bind a custom implementation in your `AppServiceProvider`:

```php
use Ometra\Caronte\Contracts\SendsTwoFactorChallenge;

$this->app->bind(SendsTwoFactorChallenge::class, YourCustomSender::class);
```

---

## 7. Initial Role Sync

```bash
# Preview
php artisan caronte:roles:sync --dry-run

# Apply
php artisan caronte:roles:sync
```

---

## 8. Post-Deployment Checklist

- [ ] `CARONTE_URL`, `CARONTE_APP_CN`, `CARONTE_APP_SECRET` present in `.env`
- [ ] `php artisan migrate` completed
- [ ] `php artisan caronte:roles:sync` completed
- [ ] `php artisan caronte:protected-api:scopes:sync` completed if this app exposes protected APIs to external clients
- [ ] Management UI accessible at `/{management.route_prefix}`
- [ ] If using Inertia management UI, `caronte:inertia` assets are published and compiled by the host app
- [ ] At least one user linked to `root` or a configured management access role via `caronte:users:roles:sync`
- [ ] `tls_verify = true` for production
- [ ] `allow_http_requests = false` for production
