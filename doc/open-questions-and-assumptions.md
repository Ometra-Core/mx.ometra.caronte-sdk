# Open Questions & Assumptions

This file tracks only unresolved items for the SDK package.

---

## Open Questions

### OQ-API-VERSIONING: Caronte server API versioning

The SDK uses hardcoded Caronte API paths without a version prefix. Breaking server API changes still require a coordinated SDK release.

### OQ-HOST-INFRA: Host application runtime defaults

This package relies on host-app choices for cache store, queue connection, and database engine. Documentation assumes Laravel defaults unless host policies override them.

### OQ-BRANCH-MAINLINE: Mainline branch naming

Repository automation currently references both `main` and `master` in different places. Release and CI branch policies should be unified to avoid confusion.

---

## Assumptions

### A-1: Caronte server availability

The package assumes the Caronte server is reachable for login, token exchange, logout, provisioning, and management operations. Infrastructure should provide HA/load balancing when needed.

### A-2: JWT key strength

`CARONTE_APP_SECRET` and `CARONTE_APPLICATION_GROUP_SECRET` must be at least 32 characters because token validation enforces minimum HMAC signing-key length.

### A-3: Single Caronte server per host application

The package reads a single `CARONTE_URL`. Multi-server setups must sit behind that URL.

### A-4: `root` role is always trusted

The `root` role satisfies all SDK role checks. The Caronte server should grant it only to fully trusted users.

### A-5: Table prefix set before first migration

If `caronte.table_prefix` changes after initial migration, tables must be renamed manually.

### A-6: Notification delivery mode is consistent

Switching from server-delivered to host-delivered 2FA/password recovery requires configuring the sender classes before deployment.
