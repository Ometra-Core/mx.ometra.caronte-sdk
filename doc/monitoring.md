# Monitoring

This package does not include a dedicated monitoring stack. Operational visibility relies on host Laravel observability and Caronte server telemetry.

## 1. Logging

Package behavior:

- Uses exceptions and structured response envelopes.
- Does not define a package-specific log channel.
- Runtime logging therefore follows host app logging configuration.

Operational recommendation:

- Ensure host app logging captures:
    - HTTP client failures to CARONTE_URL
    - 401/403 spikes on package-protected routes
    - middleware exceptions from token validation

## 2. Runtime Signals to Monitor

### Authentication health

- Login failure rate
- Token validation failures
- Unauthorized and forbidden response rates on package routes

### Integration health

- Outbound latency to Caronte API endpoints
- Outbound error rates (4xx and 5xx from Caronte)
- Retry frequency from package HTTP client

### Tenant and authorization health

- Tenant mismatch failures in single-tenant mode
- Missing scope failures for protected API routes
- Missing role failures on management routes

### OIDC mode

- Callback failures (invalid state, token validation failures)
- JWKS retrieval failures
- Refresh token exchange failures

## 3. Suggested Alerts

- Critical: sustained inability to reach CARONTE_URL
- Critical: large surge in 401 responses on core authenticated routes
- High: repeated 403 responses from role/scope checks after deployment
- High: OIDC callback failures above baseline
- Medium: elevated retry counts on Caronte HTTP calls

## 4. Troubleshooting Checklist

1. Validate package configuration:
    - caronte.url, caronte.app_cn, caronte.app_secret
    - tenancy mode and tenant id
2. Confirm token headers per scenario:
    - X-Application-Token
    - X-Group-Token when group mode is active
    - X-Tenant-Id for tenant-required flows
3. Check host route middleware order and composition.
4. Inspect HTTP payload/response details from failed Caronte requests.
5. Re-run role and scope synchronization commands after config changes.

## 5. Minimal Recommended Setup (if absent)

- Centralized host app logs with request correlation IDs
- Dashboard panels for package route 401/403/409 rates
- Synthetic check that exercises a lightweight authenticated flow
- Deployment step that executes:
    - php artisan caronte:roles:sync
    - php artisan caronte:protected-api:scopes:sync

## 6. Non-Applicable Items

- No built-in Horizon/Telescope instrumentation in this package.
- No package-owned queue workers to monitor.
