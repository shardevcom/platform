<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Definitions
    |--------------------------------------------------------------------------
    |
    | Define las plataformas/guards disponibles en la aplicación.
    | Cada plataforma representa un contexto de autenticación aislado
    | con su propio modelo de usuario, tabla, guard y prefijo de ruta.
    |
    | Claves requeridas por plataforma:
    | - key: slug único (ej. 'web', 'admin', 'employee')
    | - name: nombre legible
    | - guard: nombre del guard (debe coincidir con key)
    | - model: FQCN del modelo de usuario
    | - table: nombre de la tabla en BD
    | - provider: nombre del provider (debe coincidir con key)
    | - password_reset: nombre del broker de password reset
    | - guard_driver: 'session' | 'token' | 'passport'
    | - api_guard: nombre del guard de API con driver passport sobre este mismo
    |   provider. Solo lo necesita una plataforma con guard_driver 'session':
    |   Passport localiza el provider emisor de tokens escaneando los guards con
    |   driver 'passport' (HasApiTokens::getProviderName) y se niega a emitir
    |   uno si no encuentra ninguno.
    | - prefix: prefijo URL (ej. 'admin', 'employee')
    | - middleware: middleware adicionales para esta plataforma
    | - password_reset_table: tabla para password resets (opcional, default: {table}_password_resets)
    |
    */

    'platforms' => [
        /*
        | 'correlation' (Shardevcom\Observability\CorrelationMiddleware) se
        | aplica en todas las plataformas ANTES que 'rate.limit': registra el
        | correlation/request ID válidos (UUIDv4) y emite la línea terminal de
        | la request con las mismas dimensiones que las líneas del lifecycle y
        | de los errores (T-CASA-023).
        |
        | 'rate.limit' (Shardevcom\Security\RateLimitMiddleware) se aplica en
        | todas las plataformas. Corre antes del guard (auth) para poder contar
        | requests anónimas por IP; la identidad se resuelve de forma
        | oportunista y las operaciones caras (import/export) tienen bucket
        | propio por usuario Y tenant.
        */
        'web' => [
            'key' => 'web',
            'name' => 'Web Users',
            'guard' => 'web',
            'model' => \App\Models\User::class,
            'table' => 'users',
            'provider' => 'web',
            'password_reset' => 'web',
            'guard_driver' => 'session',
            'api_guard' => 'api',
            'prefix' => '',
            'middleware' => ['correlation', 'rate.limit', 'web'],
            'password_reset_table' => 'password_reset_tokens',
        ],

        'admin' => [
            'key' => 'admin',
            'name' => 'Admin Users',
            'guard' => 'admin',
            'model' => \App\Models\AdminUser::class,
            'table' => 'admins',
            'provider' => 'admin',
            'password_reset' => 'admin',
            'guard_driver' => 'passport',
            'prefix' => 'admin',
            'middleware' => ['correlation', 'rate.limit', 'api'],
            'password_reset_table' => 'admin_password_resets',
        ],

        'employee' => [
            'key' => 'employee',
            'name' => 'Employee Users',
            'guard' => 'employee',
            'model' => \App\Models\EmployeeUser::class,
            'table' => 'employees',
            'provider' => 'employee',
            'password_reset' => 'employee',
            'guard_driver' => 'passport',
            'prefix' => 'employee',
            'middleware' => ['correlation', 'rate.limit', 'api'],
            'password_reset_table' => 'employee_password_resets',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Platform
    |--------------------------------------------------------------------------
    |
    | Plataforma por defecto para rutas sin prefijo de guard.
    |
    */
    'default' => env('PLATFORM_DEFAULT', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Platform Resolution
    |--------------------------------------------------------------------------
    |
    | Cómo resolver la plataforma actual desde la request.
    |
    */
    'resolution' => [
        'from_route_prefix' => true,
        'from_header' => 'X-Platform-Guard',
        'fallback_to_default' => true,
    ],
];