<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Caronte Server Configuration
    |--------------------------------------------------------------------------
    */
    'url'        => env('CARONTE_URL', ''),
    'app_cn'     => env('CARONTE_APP_CN', ''),
    'app_secret' => env('CARONTE_APP_SECRET', ''),

    'application_group_id' => env('CARONTE_APPLICATION_GROUP_ID', ''),
    'application_group_secret' => env('CARONTE_APPLICATION_GROUP_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | JWT Token Configuration
    |--------------------------------------------------------------------------
    */
    'issuer_id'                => env('CARONTE_ISSUER_ID', 'caronte'),
    'auth_mode'                => env('CARONTE_AUTH_MODE', 'jwt'), // jwt, oidc, dual

    'token' => [
        'ttl_seconds' => (int) env('CARONTE_TOKEN_TTL_SECONDS', 300),
        'clock_skew_seconds' => (int) env('CARONTE_TOKEN_CLOCK_SKEW_SECONDS', 60),
        'refresh_leeway_seconds' => (int) env('CARONTE_TOKEN_REFRESH_LEEWAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenID Connect Configuration
    |--------------------------------------------------------------------------
    */
    'oidc' => [
        'issuer'        => env('CARONTE_OIDC_ISSUER', env('CARONTE_URL', '')),
        'client_id'     => env('CARONTE_OIDC_CLIENT_ID', env('CARONTE_APP_CN', '')),
        'client_secret' => env('CARONTE_OIDC_CLIENT_SECRET', ''),
        'redirect_uri'  => env('CARONTE_OIDC_REDIRECT_URI', ''),
        'scopes'        => env('CARONTE_OIDC_SCOPES', 'openid profile email'),
        'jwks_cache_ttl' => (int) env('CARONTE_OIDC_JWKS_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Features
    |--------------------------------------------------------------------------
    */
    'use_2fa'               => env('CARONTE_2FA', false),
    'allow_http_requests'   => env('CARONTE_ALLOW_HTTP_REQUESTS', false),
    'tls_verify'            => env('CARONTE_TLS_VERIFY', true),
    'update_local_user'     => env('CARONTE_UPDATE_LOCAL_USER', false),
    'notification_delivery' => env('CARONTE_NOTIFICATION_DELIVERY', 'server'),
    'session_key'           => env('CARONTE_SESSION_KEY', 'caronte.user_token'),
    'tenant_tokens_session_key' => env('CARONTE_TENANT_TOKENS_SESSION_KEY', 'caronte.tenant_tokens'),
    'last_tenant_session_key' => env('CARONTE_LAST_TENANT_SESSION_KEY', 'caronte.last_tenant_id'),

    'notifications' => [
        'two_factor_sender'       => env(
            'CARONTE_TWO_FACTOR_SENDER',
            Ometra\Caronte\Notifications\TwoFactorChallengeSender::class
        ),
        'password_recovery_sender' => env(
            'CARONTE_PASSWORD_RECOVERY_SENDER',
            Ometra\Caronte\Notifications\PasswordRecoverySender::class
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'auth_enabled' => env('CARONTE_AUTH_ROUTES_ENABLED', true),
        'prefix' => env('CARONTE_ROUTES_PREFIX', ''),
        'success_url' => env('CARONTE_SUCCESS_URL', '/'),
        'login_url' => env('CARONTE_LOGIN_URL', '/login'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback URL Security
    |--------------------------------------------------------------------------
    |
    | strict_same_host: Requires same scheme, host and port as current request.
    | allowlist: Allows absolute callback hosts when explicitly listed. A
    | leading wildcard (for example, *.example.com) allows subdomains only;
    | list example.com separately when the root domain must also be allowed.
    |
    */
    'callback_url' => [
        'policy' => env('CARONTE_CALLBACK_URL_POLICY', 'strict_same_host'),
        'allowed_hosts' => array_values(
            array_filter(
                array_map('trim', explode(',', (string) env('CARONTE_CALLBACK_URL_ALLOWED_HOSTS', '')))
            )
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy Mode
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        'mode'      => env('CARONTE_TENANCY_MODE', 'multi'),
        'id_tenant' => env('CARONTE_TENANT_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout'     => (int) env('CARONTE_HTTP_TIMEOUT', 10),
        'retries'     => (int) env('CARONTE_HTTP_RETRIES', 1),
        'retry_sleep' => (int) env('CARONTE_HTTP_RETRY_SLEEP', 150),
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Roles
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'root' => 'Default super administrator role',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected API Scopes
    |--------------------------------------------------------------------------
    */
    'protected_api' => [
        'scopes' => [
            // 'invoices.read' => 'Read invoices through this application API',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */
    'management' => [
        'enabled'      => env('CARONTE_MANAGEMENT_ENABLED', true),
        'route_prefix' => env('CARONTE_MANAGEMENT_ROUTE_PREFIX', 'caronte/management'),
        'access_roles' => array_values(
            array_filter(
                array_map('trim', explode(',', (string) env('CARONTE_MANAGEMENT_ACCESS_ROLES', 'root')))
            )
        ),
        'features' => [
            'metadata' => env('CARONTE_MANAGEMENT_METADATA', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | View & UI Configuration
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'use_inertia' => env('CARONTE_USE_INERTIA', false),
        'branding' => [
            'app_name'      => env('CARONTE_UI_APP_NAME', env('APP_NAME', 'Caronte')),
            'headline'      => env('CARONTE_UI_HEADLINE', 'Secure access with Caronte'),
            'subheadline'   => env(
                'CARONTE_UI_SUBHEADLINE',
                'Authenticate users and administer access from a polished package surface.'
            ),
            'support_email' => env('CARONTE_UI_SUPPORT_EMAIL', ''),
            'logo_url'      => env('CARONTE_UI_LOGO_URL', ''),
            'accent'        => env('CARONTE_UI_ACCENT', '#0f766e'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Prefix
    |--------------------------------------------------------------------------
    */
    'table_prefix' => env('CARONTE_TABLE_PREFIX', ''),
];
