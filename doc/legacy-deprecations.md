# SDK legacy deprecations

SDK 4.x continues to accept legacy contracts during migration and reports each deprecated feature once per process. Deprecated HTTP middleware and management routes also return `Deprecation: true`.

| Deprecated | Replacement | Removal |
| --- | --- | --- |
| `CARONTE_AUTH_MODE=legacy` | `CARONTE_AUTH_MODE=jwt` | SDK 5 |
| Nested JWT claim `user` | Top-level user claims | SDK 5 |
| `caronte.permissions` and entry key `permission` for protected APIs | `caronte.protected_api.scopes` and `scope` | SDK 5 |
| JWT claim `permissions` | `scopes` | SDK 5 |
| Audience `application_token` | `protected_api_access` | SDK 5 |
| `caronte.app-permissions` | `caronte.protected-api-scopes` | SDK 5 |
| `caronte:permissions:sync` | `caronte:protected-api:scopes:sync` | SDK 5 |
| POST management user/role mutation wrappers | Direct REST user routes and role sync | SDK 5 |

Before upgrading, run representative requests with log monitoring enabled and eliminate every SDK deprecation warning and `Deprecation` response header.
