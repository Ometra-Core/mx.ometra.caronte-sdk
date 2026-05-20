# Admin Handover

This handover is for Caronte server/admin maintainers. It describes what the
central Caronte system must provide so Laravel host applications using
`ometra/caronte-sdk` can adopt authentication and protected API security without
friction.

## Responsibilities

Caronte server/admin is responsible for:

- Registering host applications and their `app_cn` / `app_secret` credentials.
- Registering application groups and group secrets.
- Receiving protected API scope syncs from host applications.
- Letting admins grant scopes to external clients per target app and tenant.
- Issuing Protected API Access Tokens for external clients.

Caronte server/admin must not delegate Protected API Access Token signing to
external clients. External clients request tokens from Caronte; they do not
create or sign their own tokens.

## Token Taxonomy

| Token type | Issuer | Consumer | Transport | Purpose |
| ---------- | ------ | -------- | --------- | ------- |
| User Token | Caronte auth server | Browser/API user and host SDK | Web session or `Authorization: Bearer <user-jwt>` | Human authentication |
| Application Auth Token | Host SDK | Caronte server or another host app | `X-Application-Token` | Internal app identity |
| Application Group Auth Token | Host SDK | Caronte server or another host app | `X-Group-Token` plus `X-Application-Token` | Internal group membership and source app traceability |
| Protected API Access Token | Caronte server/admin | External API consumer and target host app | `Authorization: Bearer <protected-api-access-token>` | External API access to target app scopes |

## Application Registration

For each host app, Caronte must store:

- canonical app name (`app_cn`);
- derived app id (`sha1(lowercase(trim(app_cn)))`);
- app signing secret;
- allowed tenants;
- declared user roles;
- declared protected API scopes.

The app signing secret must be at least 32 characters. It is shared only between
Caronte and the target host app.

## Application Groups

For apps that share group-authenticated user tokens or app-to-app credentials,
Caronte must store:

- `group_id`;
- group signing secret;
- member applications;
- source app traceability rules.

Host apps in a group send both:

```http
X-Application-Token: <application-auth-jwt>
X-Group-Token: <application-group-auth-jwt>
```

## Protected API Scope Sync

Host apps declare scopes and push them to Caronte.

Target endpoint:

```http
PUT /api/applications/scopes
X-Application-Token: <application-auth-jwt>
X-Group-Token: <application-group-auth-jwt>  # only when the app is grouped
Content-Type: application/json
```

Payload:

```json
{
  "scopes": [
    {
      "scope": "invoices.read",
      "description": "Read invoices"
    },
    {
      "scope": "invoices.write",
      "description": "Write invoices"
    }
  ]
}
```

Caronte should store scopes against the target app id resolved from the
Application Auth Token. Scope names are lowercase identifiers such as
`invoices.read`, `orders:write`, or `reports.export`.

## Protected API Access Token Issuance

Caronte server/admin emits Protected API Access Tokens for external clients.
Issuance must require:

- target host app;
- tenant id;
- approved scope list;
- token name or integration label;
- expiration policy.

The external client receives the token and calls the target host app with:

```http
Authorization: Bearer <protected-api-access-token>
```

The host app validates the token locally. Caronte should not require per-request
introspection for normal protected API access.

## Protected API Access Token Structure

Required JWT claims:

| Claim | Requirement |
| ----- | ----------- |
| `jti` | Unique token id |
| `iss` | Caronte issuer id |
| `aud` | Target host app id |
| `iat` | Issued-at timestamp |
| `nbf` | Not-before timestamp |
| `exp` | Expiration timestamp |
| `token_audience` | Must be `protected_api_access` |
| `app_id` | Target host app id |
| `tenant_id` | Tenant the token is bound to |
| `scopes` | Array of granted scope names |
| `name` | Optional integration label |

Example payload:

```json
{
  "iss": "caronte",
  "aud": "9c3a0b8f0f...",
  "jti": "pat_01HV...",
  "iat": 1714070400,
  "nbf": 1714070400,
  "exp": 1745606400,
  "token_audience": "protected_api_access",
  "app_id": "9c3a0b8f0f...",
  "tenant_id": "tenant-1",
  "name": "Billing integration",
  "scopes": ["invoices.read"]
}
```

The token must be signed with the target host app's `CARONTE_APP_SECRET`.

## User Token Notes

User tokens are separate from Protected API Access Tokens.

- Web host routes store the user token in session.
- API host routes protected by `caronte.session` use
  `Authorization: Bearer <user-jwt>`.
- `X-User-Token` is used for SDK forwarding and refreshed-token responses, not
  as the primary incoming auth header for host API routes.

## Deprecated Legacy Names

These names remain available only for compatibility in this version. They must
be removed in the next major version:

- Classes named `CaronteApplicationAccess*`.
- Middleware alias `caronte.app-token`.
- Middleware alias `caronte.app-permissions`.
- Config key `permissions`.
- Command `caronte:permissions:sync`.
- Endpoint, payload, or JWT claim named `permissions`.

Caronte server/admin should migrate new work to `scopes` and
`protected_api_access` now, and treat `permissions` as a temporary compatibility
bridge only.
