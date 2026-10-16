<?php

declare(strict_types=1);

/**
 * Documentation package configuration.
 */
return [
    /*
     |--------------------------------------------------------------------------
     | Documentation Generation
     |--------------------------------------------------------------------------
     |
     | Configuration for generating API documentation from the application.
     |
     */

    'generation' => [
        /*
         |--------------------------------------------------------------------------
         | Output formats
         |--------------------------------------------------------------------------
         |
         | Supported formats: 'openapi-json', 'openapi-yaml', 'html', 'markdown',
         | 'graphql-sdl', 'graphql-introspection', 'postman', 'insomnia'
         |
         */
        'formats' => ['openapi-json', 'html', 'graphql-sdl'],

        /*
         |--------------------------------------------------------------------------
         | Output directory
         |--------------------------------------------------------------------------
         |
         | Where to write generated documentation files.
         |
         */
        'output_dir' => 'public/docs',

        /*
         |--------------------------------------------------------------------------
         | API metadata
         |--------------------------------------------------------------------------
         |
         | Information used in the generated OpenAPI info object.
         |
         */
        'info' => [
            'title' => env('APP_NAME', 'Shardev Platform API'),
            'version' => env('PLATFORM_VERSION', '1.0.0'),
            'description' => 'API documentation for Shardev Platform',
            'contact' => [
                'name' => 'Shardev Platform',
                'url' => 'https://shardev.com',
                'email' => 'support@shardev.com',
            ],
            'license' => [
                'name' => 'MIT',
                'url' => 'https://opensource.org/licenses/MIT',
            ],
        ],

        /*
         |--------------------------------------------------------------------------
         | Servers
         |--------------------------------------------------------------------------
         |
         | Server URLs for the OpenAPI specification.
         |
         */
        'servers' => [
            [
                'url' => env('APP_URL', 'http://localhost'),
                'description' => 'Local development server',
            ],
        ],

        /*
         |--------------------------------------------------------------------------
         | Security schemes
         |--------------------------------------------------------------------------
         |
         | Authentication methods supported by the API.
         |
         */
        'security' => [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'Laravel Passport access token',
            ],
            'apiKey' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
                'description' => 'API key authentication',
            ],
        ],

        /*
         |--------------------------------------------------------------------------
         | Tags for grouping endpoints
         |--------------------------------------------------------------------------
         |
         */
        'tags' => [
            ['name' => 'Products', 'description' => 'Product management endpoints'],
            ['name' => 'Configuration', 'description' => 'Platform configuration endpoints'],
            ['name' => 'Widgets', 'description' => 'Dashboard widget endpoints'],
            ['name' => 'Authentication', 'description' => 'Authentication and authorization'],
            ['name' => 'Import/Export', 'description' => 'Data import and export operations'],
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Parsing configuration
     |--------------------------------------------------------------------------
     |
     | How to extract documentation from the codebase.
     |
     */
    'parsing' => [
        /*
         |--------------------------------------------------------------------------
         | Route patterns to include/exclude
         |--------------------------------------------------------------------------
         */
        'routes' => [
            'include' => ['api/*', 'admin/*', 'employee/*', 'graphql'],
            'exclude' => ['_debugbar/*', 'telescope/*', 'horizon/*'],
        ],

        /*
         |--------------------------------------------------------------------------
         | Controller parsing
         |--------------------------------------------------------------------------
         |
         | Extract documentation from controller docblocks and attributes.
         |
         */
        'controllers' => [
            'enabled' => true,
            'paths' => [
                'app/Http/Controllers',
                'packages/*/src/Rest/Http/Controllers',
            ],
            'extract_docblocks' => true,
            'extract_attributes' => true,
        ],

        /*
         |--------------------------------------------------------------------------
         | FormRequest parsing
         |--------------------------------------------------------------------------
         |
         | Extract validation rules from FormRequest classes.
         |
         */
        'form_requests' => [
            'enabled' => true,
            'paths' => [
                'app/Http/Requests',
                'packages/*/src/Rest/Http/Requests',
            ],
        ],

        /*
         |--------------------------------------------------------------------------
         | GraphQL parsing
         |--------------------------------------------------------------------------
         |
         | Extract documentation from GraphQL schema and resolvers.
         |
         */
        'graphql' => [
            'enabled' => true,
            'schema_path' => 'graphql/schema.graphql',
            'resolvers_paths' => [
                'app/GraphQL',
                'packages/*/src/Graphql/Resolvers',
            ],
            'extract_docblocks' => true,
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | UI Configuration
     |--------------------------------------------------------------------------
     |
     | Configuration for the documentation UI (Swagger UI, Redoc, etc.)
     |
     */
    'ui' => [
        'enabled' => true,
        'route_prefix' => 'docs',
        'default_format' => 'html', // 'html', 'redoc', 'swagger'
        'swagger_ui' => [
            'persist_authorization' => true,
            'display_request_duration' => true,
            'filter' => true,
            'deep_linking' => true,
        ],
        'redoc' => [
            'hide_hostname' => false,
            'expand_responses' => 'all',
            'required_props_first' => true,
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Lifecycle hooks
     |--------------------------------------------------------------------------
     |
     | Callbacks to customize documentation generation.
     |
     */
    'hooks' => [
        'before_generate' => [],
        'after_generate' => [],
        'customize_openapi' => [],
        'customize_graphql' => [],
    ],
];