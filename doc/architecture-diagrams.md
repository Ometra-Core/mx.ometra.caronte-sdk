# Architecture Diagrams

---

## 1. System Context

```mermaid
C4Context
    title System Context — Caronte Authentication

    Person(user, "End User", "Browser or API client")
    System(hostApp, "Host Application", "Laravel app using ometra/caronte-client")
    System_Ext(caronte, "Caronte Server", "Central authentication & role server")
    SystemDb(db, "Host DB", "Stores local user cache (CaronteUser)")

    Rel(user, hostApp, "HTTP requests")
    Rel(hostApp, caronte, "JWT exchange / user & role management", "HTTPS")
    Rel(hostApp, db, "Read/write local user cache", "Eloquent")
```

---

## 2. Container Diagram

```mermaid
C4Container
    title Container — Host Application with Caronte Client

    Container(web, "Web Layer", "Laravel Router + Middleware", "Handles HTTP, applies caronte.session / caronte.roles middleware")
    Container(pkg, "caronte-client package", "PHP library", "Auth flow, token validation, role management, management UI")
    Container(apiClients, "API Clients", "CaronteApiClient / CaronteServiceClient", "Outgoing HTTP to Caronte server")
    Container(db, "Local DB", "MySQL", "CaronteUser / CaronteUserMetadata tables")
    System_Ext(caronte, "Caronte Server", "Issues and validates JWTs, stores canonical roles & users")

    Rel(web, pkg, "Middleware, Controller, Facade calls")
    Rel(pkg, apiClients, "Delegates server calls")
    Rel(apiClients, caronte, "REST over HTTPS")
    Rel(pkg, db, "Eloquent ORM")
```

---

## 3. Component Diagram

```mermaid
graph TD
    subgraph Middleware
        A[caronte.session<br/>ValidateUserToken]
        B[caronte.roles<br/>ValidateUserRoles]
        C[caronte.application<br/>ResolveApplicationContext]
    end

    subgraph Controllers
        D[AuthController<br/>login / logout / 2FA / recovery]
        E[ManagementController<br/>dashboard / sync]
        F[UserController<br/>CRUD]
        G[RoleController<br/>sync]
    end

    subgraph Facade & Core
        H[Caronte facade<br/>getToken / checkToken / getUser]
        I[CaronteUserToken<br/>validateToken / exchange]
        J[PermissionHelper<br/>hasRoles / hasApplication]
    end

    subgraph API Layer
        K[AuthApi]
        L[ClientApi]
        M[RoleApi]
        N[CaronteApiClient<br/>extends CaronteHttpClient]
    end

    subgraph Support
        O[CaronteApplicationToken<br/>make / matches]
        P[CaronteHttpClient<br/>request / parseResponse]
        Q[ConfiguredRoles<br/>all / names / accessRoles]
        R[CaronteServiceClient<br/>inter-service calls]
    end

    A --> H
    A --> I
    B --> J
    C --> O
    D --> K
    D --> I
    E --> L
    F --> L
    G --> M
    K --> N
    L --> N
    M --> N
    N --> P
    R --> P
    H --> I
```

---

## 4. Authentication Flow Sequence

```mermaid
sequenceDiagram
    participant U as User (Browser)
    participant H as Host App
    participant C as Caronte Server

    U->>H: POST /caronte/login (email, password)
    H->>C: POST /api/auth/login (app token header)
    C-->>H: { data: { token: <JWT> } }
    H->>H: Store JWT in session
    H-->>U: Redirect to success_url

    Note over H,C: Subsequent requests

    U->>H: GET /protected-route (session cookie)
    H->>H: ValidateUserToken middleware
    H->>H: CaronteUserToken::validateToken(JWT)
    alt Token valid & not expired
        H-->>U: 200 OK
    else Token expired
        H->>C: POST /api/auth/exchange (old JWT)
        C-->>H: { data: { token: <new JWT> } }
        H->>H: Update session
        H-->>U: 200 OK + X-User-Token header
    else Token invalid
        H-->>U: Redirect to login
    end
```

---

## 5. Application Token Flow

```mermaid
sequenceDiagram
    participant S as Service A (caller)
    participant T as Service B (target)

    S->>S: CaronteApplicationToken::make()
    Note right of S: short-lived JWT signed with app secret
    opt Application group configured
        S->>S: CaronteApplicationToken::makeGroup()
    end
    S->>T: HTTP request + X-Application-Token (+ X-Group-Token)
    T->>T: ResolveApplicationContext middleware
    T->>T: Validate app/group JWT headers
    alt Valid
        T->>T: Bind CaronteApplicationContext to IoC
        T-->>S: 200 OK
    else Invalid
        T-->>S: 401 Unauthorized
    end
```

---

## 6. Package Directory Structure

```
src/
├── Caronte.php                  # Main facade class (user token management)
├── CaronteServiceClient.php     # Inter-service HTTP client (public API)
├── CaronteUserToken.php         # JWT parse/validate/exchange
├── CaronteProtectedApiAccessToken.php # Protected API bearer token validation
├── CaronteApplicationAccessToken.php  # Deprecated compatibility alias
├── Api/
│   ├── AuthApi.php              # Static proxy — auth endpoints
│   ├── CaronteApiClient.php     # HTTP client for Caronte server
│   ├── ClientApi.php            # Static proxy — user endpoints
│   ├── RoleApi.php              # Static proxy — role endpoints
│   ├── ScopeApi.php             # Static proxy — protected API scope endpoints
│   └── PermissionApi.php        # Deprecated compatibility alias
├── Console/Commands/            # Artisan commands
├── Contracts/                   # SendsTwoFactorChallenge, SendsPasswordRecovery
├── Facades/                     # Caronte facade alias
├── Helpers/
│   ├── CaronteUserHelper.php
│   └── PermissionHelper.php
├── Http/
│   ├── Controllers/             # Auth, Management, User, Role controllers
│   └── Middleware/              # Session, roles, app auth, protected API token/scopes
├── Mail/                        # Mailable classes for host-delivery mode
├── Models/                      # CaronteUser, CaronteUserMetadata
├── Notifications/               # Default sender implementations
├── Providers/
│   └── CaronteServiceProvider.php
└── Support/
    ├── CaronteApplicationContext.php  # DTO bound by ResolveApplicationContext
    ├── CaronteProtectedApiAccessContext.php # DTO bound by ValidateProtectedApiAccessToken
    ├── CaronteApplicationAccessContext.php  # Deprecated compatibility alias
    ├── CaronteTenancy.php              # Tenancy mode validation and tenant binding
    ├── CaronteApplicationToken.php    # App token generation & validation
    ├── CaronteHttpClient.php          # Abstract HTTP base (template method)
    ├── CaronteResponse.php            # Normalised response DTO
    ├── ConfiguredRoles.php            # Reads config('caronte.roles')
    ├── ConfiguredScopes.php           # Reads config('caronte.protected_api.scopes')
    └── ConfiguredPermissions.php      # Deprecated compatibility alias
```
