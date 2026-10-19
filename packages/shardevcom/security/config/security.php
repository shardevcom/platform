<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Cross-tenant IDOR protection and CSP configuration.
    |
    */

    'idor' => [
        'enabled' => true,
        'tenant_id_parameters' => ['tenant_id', 'tenantId'],
        'protected_routes' => [
            // Add route names that require strict tenant isolation
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | First-party frontend
    |--------------------------------------------------------------------------
    |
    | Base URL of the SPA that owns the account screens. Recovery and
    | verification emails link here:
    |   {url}{password_reset_path}?token=&email=&guard=
    |   {url}{email_verification_path}?verify_url=<signed api url>&guard=
    | Leave `url` empty to keep the raw API signed URL (useful in dev, where
    | the link is read from the mail log).
    |
    */
    'frontend' => [
        'url' => env('FRONTEND_URL', env('PASSWORD_RESET_FRONTEND_URL', '')),
        'password_reset_path' => '/reset-password',
        'email_verification_path' => '/verify-email',
        'invitation_path' => '/accept-invitation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    |
    | How long an invitation stays valid before it must be reissued.
    |
    */
    'invitations' => [
        'ttl_hours' => (int) env('INVITATIONS_TTL_HOURS', 72),
    ],

    'csp' => [
        'enabled' => true,
        'report_only' => false,
        'report_uri' => null,
        'directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'"],
            'connect-src' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ],
        'report_uri' => null,
        'report_only' => false,
    ],

    'headers' => [
        'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),

        /*
        | COEP deliberadamente AUSENTE: require-corp rompe consumidores JSON
        | cross-origin (T-CASA-018). COOP/CORP sí proceden en una API JSON y
        | van en cada request sin wildcard.
        */
        'content_type_options' => ['enabled' => true, 'value' => 'nosniff'],
        'frame_options' => ['enabled' => true, 'value' => 'DENY'],
        'referrer_policy' => ['enabled' => true, 'value' => 'strict-origin-when-cross-origin'],
        'permissions_policy' => ['enabled' => true, 'value' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()'],
        'cross_origin_opener_policy' => ['enabled' => true, 'value' => 'same-origin'],
        'cross_origin_resource_policy' => ['enabled' => true, 'value' => 'same-site'],

        /*
        | HSTS solo se emite sobre HTTPS. emit_http=true es para despliegues
        | http-only (tests/CI sin TLS propio detrás de un proxy que sí lo
        | emite).
        */
        'hsts' => [
            'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
            'max_age' => (string) env('SECURITY_HSTS_MAX_AGE', '31536000'),
            'include_subdomains' => true,
            'emit_http' => (bool) env('SECURITY_HSTS_EMIT_HTTP', false),
        ],

        /*
        | CSP solo donde haya HTML (responses text/html). Una API JSON no la
        | lleva y no puede romper el fetch cross-origin.
        */
        'csp' => [
            'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
        ],
    ],

    'rate_limit' => [
        'enabled' => (bool) env('RATE_LIMIT_ENABLED', true),

        /*
        | Store compartido. Si la aplicación corre con más de una instancia
        | (horizontal scaling), este store DEBE ser compartido (p.ej. Redis).
        | null => almacén de cache por defecto de la app.
        */
        'store' => env('RATE_LIMIT_STORE'),

        /*
        | Límite base anónimo: por IP.
        | Se aplica pre-auth: la identidad se resuelve de forma oportunista y
        | un token ausente o inválido se cuenta como anónimo (IP).
        */
        'anonymous' => [
            'limit' => (int) env('RATE_LIMIT_ANONYMOUS_LIMIT', 30),
            'decay_seconds' => 60,
        ],

        /*
        | Límite base autenticado: por usuario.
        | Remplaza al bucket anónimo cuando la request trae un token válido.
        */
        'authenticated' => [
            'limit' => (int) env('RATE_LIMIT_AUTHENTICATED_LIMIT', 120),
            'decay_seconds' => 60,
        ],

        /*
        | Límites por operación cara (import/export). Se aplican ADEMÁS del
        | bucket base, con scope por usuario Y por tenant: la clave incluye
        | route name + userId + tenantId, de modo que dos tenants no comparten
        | contadores ni una operación aquí agota el límite global del usuario.
        */
        'operations' => [
            'products.import' => [
                'limit' => (int) env('RATE_LIMIT_IMPORT_LIMIT', 10),
                'decay_seconds' => 3600,
            ],
            'products.export' => [
                'limit' => (int) env('RATE_LIMIT_EXPORT_LIMIT', 20),
                'decay_seconds' => 3600,
            ],

            /*
            | GraphQL: el endpoint /graphql no tiene route name por operación,
            | así que esta clave agrupa TODAS las mutaciones (por usuario Y por
            | tenant). Las queries van con los buckets base.
            */
            'graphql_mutation' => [
                'limit' => (int) env('RATE_LIMIT_GRAPHQL_MUTATION_LIMIT', 10),
                'decay_seconds' => 60,
            ],
        ],
    ],
];