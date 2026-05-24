# Architecture Diagrams

## System Context Diagram

```mermaid
flowchart LR
  U[End User]
  A[Host Laravel Application]
  P[ometra/caronte-sdk Package]
  C[Caronte Authentication Server]
  O[OIDC Issuer]

  U --> A
  A --> P
  P --> C
  P --> O
  C --> P
  O --> P
  P --> A
```

## Container Diagram

```mermaid
flowchart TB
  subgraph HostApp[Host Laravel Application]
    WEB[Web Controllers + Package Routes]
    MW[Package Middleware Layer]
    CLI[Artisan Commands]
    DB[(Host DB: Users + UsersMetadata)]
    CACHE[(Host Cache: OIDC JWKS)]
  end

  CAR[Caronte Server API]
  OIDC[OIDC Endpoints]

  WEB --> MW
  MW --> CAR
  WEB --> CAR
  CLI --> CAR
  WEB --> DB
  MW --> DB
  MW --> CACHE
  MW --> OIDC
```

## Component Diagram

```mermaid
flowchart LR
  subgraph EntryPoints[HTTP Entry Points]
    AuthCtrl[AuthController]
    OidcCtrl[OidcAuthController]
    MgmtCtrl[ManagementController]
    RoleCtrl[RoleController]
    UserCtrl[UserController]
  end

  subgraph Middleware[Middleware]
    SessionMW[ValidateUserToken]
    RolesMW[ValidateUserRoles]
    AppCtxMW[ResolveApplicationContext]
    PTokenMW[ValidateProtectedApiAccessToken]
    PScopeMW[ValidateProtectedApiScopes]
  end

  subgraph Domain[Core Domain]
    Facade[Caronte Facade]
    UserToken[CaronteUserToken]
    AppToken[CaronteApplicationToken]
    ScopeCfg[ConfiguredScopes]
    RoleCfg[ConfiguredRoles]
    Tenancy[CaronteTenancy]
  end

  subgraph Integration[Outbound API]
    AuthApi[Api/AuthApi]
    ClientApi[Api/ClientApi]
    RoleApi[Api/RoleApi]
    ScopeApi[Api/ScopeApi]
    TenantApi[Api/TenantApi]
    HttpClient[CaronteApiClient + CaronteHttpClient]
  end

  AuthCtrl --> AuthApi
  OidcCtrl --> Facade
  MgmtCtrl --> ClientApi
  MgmtCtrl --> RoleApi
  RoleCtrl --> RoleApi
  UserCtrl --> ClientApi

  SessionMW --> Facade
  SessionMW --> UserToken
  SessionMW --> Tenancy
  AppCtxMW --> AppToken
  PTokenMW --> AppToken
  PScopeMW --> ScopeCfg

  AuthApi --> HttpClient
  ClientApi --> HttpClient
  RoleApi --> HttpClient
  ScopeApi --> HttpClient
  TenantApi --> HttpClient

  RoleCfg --> RoleApi
  ScopeCfg --> ScopeApi
```

## Notes

- This is a package-level architecture view; host app architecture is intentionally not modeled in detail.
- Deprecated compatibility components (permission aliases and legacy middleware aliases) remain present and should be considered in migration planning.
