# Legacy removals in SDK 6.0.0

SDK 6.0.0 removes the compatibility contracts that remained available in SDK 5.x.

| Removed | Replacement | Removal |
| --- | --- | --- |
| `CARONTE_AUTH_MODE=legacy` | `CARONTE_AUTH_MODE=jwt` | SDK 6.0.0 |
| Nested JWT claim `user` | Top-level user claims | SDK 6.0.0 |
| `caronte.permissions` and entry key `permission` for protected APIs | `caronte.protected_api.scopes` and `scope` | SDK 6.0.0 |
| JWT claim `permissions` | `scopes` | SDK 6.0.0 |
| Audience `application_token` | `protected_api_access` | SDK 6.0.0 |
| `caronte.app-permissions` | `caronte.protected-api-scopes` | SDK 6.0.0 |
| `caronte:permissions:sync` | `caronte:protected-api:scopes:sync` | SDK 6.0.0 |
| POST management user/role mutation wrappers | Direct REST user routes and role sync | SDK 6.0.0 |

Before upgrading, replace every removed contract listed above. SDK 6.0.0 no longer emits deprecation warnings because those paths no longer exist.
