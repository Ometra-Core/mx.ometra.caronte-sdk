# Deployment Instructions

This package is integrated into a host Laravel application. Deployment guidance below is for the host app where ometra/caronte-sdk is installed.

## 1. System Requirements

- PHP: ^8.2
- Laravel: ^12.0
- Composer
- Database supported by host Laravel app (package ships migrations for local user cache tables)
- Network connectivity to CARONTE_URL
- Optional for OIDC mode:
    - Reachable OIDC issuer
    - Working Laravel cache store for JWKS caching

Relevant package dependencies:

- lcobucci/jwt and lcobucci/clock
- inertiajs/inertia-laravel (optional UI mode)
- laravel/prompts (interactive artisan commands)

## 2. Environment Configuration

Minimum required variables:

- CARONTE_URL=https://caronte.example.com
- CARONTE_APP_CN=your-app-canonical-name
- CARONTE_APP_SECRET=YOUR_APP_SECRET_MIN_32_CHARS

Common optional variables:

- CARONTE_ISSUER_ID=caronte
- CARONTE_ENFORCE_ISSUER=true
- CARONTE_AUTH_MODE=jwt

Run `php artisan migrate` during the SDK upgrade. The tenant-column standardization migration backfills `Users.tenant_id` from legacy `Users.id_tenant` and then removes `id_tenant`. If any row has neither value, the migration stops without dropping the legacy column; repair those rows explicitly and rerun it.
- CARONTE_LOGIN_URL=/login
- CARONTE_SUCCESS_URL=/
- CARONTE_SESSION_KEY=caronte.user_token
- CARONTE_TENANCY_MODE=multi
- CARONTE_TENANT_ID=tenant-id-when-single-tenant
- CARONTE_MANAGEMENT_ENABLED=true
- CARONTE_MANAGEMENT_ROUTE_PREFIX=caronte/management
- CARONTE_MANAGEMENT_ACCESS_ROLES=root
- CARONTE_MANAGEMENT_USE_INERTIA=false
- CARONTE_HTTP_TIMEOUT=10
- CARONTE_HTTP_RETRIES=1
- CARONTE_HTTP_RETRY_SLEEP=150
- CARONTE_TLS_VERIFY=true
- CARONTE_ALLOW_HTTP_REQUESTS=false

Optional application-group auth:

- CARONTE_APPLICATION_GROUP_ID=group-id
- CARONTE_APPLICATION_GROUP_SECRET=YOUR_GROUP_SECRET_MIN_32_CHARS

Optional OIDC variables:

- CARONTE_OIDC_ISSUER=https://issuer.example.com
- CARONTE_OIDC_CLIENT_ID=client-id
- CARONTE_OIDC_CLIENT_SECRET=client-secret
- CARONTE_OIDC_REDIRECT_URI=https://host.example.com/oidc/callback
- CARONTE_OIDC_SCOPES=openid profile email

## 3. Initial Setup in Host App

1. Install package:

    composer require ometra/caronte-sdk

2. Publish config:

    php artisan vendor:publish --tag=caronte:config

3. Publish migrations (if you want host-owned migration files):

    php artisan vendor:publish --tag=caronte:migrations

4. Run migrations:

    php artisan migrate

5. Optional publishing:
    - php artisan vendor:publish --tag=caronte:views
    - php artisan vendor:publish --tag=caronte-assets
    - php artisan vendor:publish --tag=caronte:inertia

6. Synchronize roles/scopes after configuring caronte.roles and caronte.protected_api.scopes:
    - php artisan caronte:roles:sync
    - php artisan caronte:protected-api:scopes:sync

## 4. Host Route Integration

Use package middleware on host routes as needed:

- caronte.session: validates user token and tenant access
- caronte.roles:role1,role2: role checks
- caronte.application[:tenant_required,user_required]: app-to-app context
- caronte.protected-api-token + caronte.protected-api-scopes:scope: protected API access

## 5. Deployment Workflow (Staging/Production)

Recommended sequence in host app:

1. Pull code
2. composer install --no-dev --prefer-dist --optimize-autoloader
3. php artisan config:cache
4. php artisan route:cache (only if your app supports route caching)
5. php artisan migrate --force
6. php artisan caronte:roles:sync
7. php artisan caronte:protected-api:scopes:sync
8. Restart PHP workers / FPM as applicable

Notes:

- Package itself does not require queue workers or scheduler entries.
- If host app uses OIDC mode, ensure cache backend is healthy because JWKS are cached.

## 6. Local Development

Typical local cycle:

- Configure .env with Caronte credentials
- php artisan migrate
- php artisan test
- Open /login and /caronte/management

If CARONTE_URL is http in local, set CARONTE_ALLOW_HTTP_REQUESTS=true explicitly.

## 7. Assumptions

- CI/CD, container images, and infrastructure automation belong to the host app.
- No package-owned cron tasks are required.

Open items are tracked in doc/open-questions-and-assumptions.md.
