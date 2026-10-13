<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shardev\Documentation\Contracts\DocumentationGenerator;

/*
 |--------------------------------------------------------------------------
 | Documentation UI Routes
 |--------------------------------------------------------------------------
 |
 | These routes serve the generated API documentation UI.
 | They are registered by DocumentationServiceProvider when UI is enabled.
 |
 */

Route::prefix(config('documentation.ui.route_prefix', 'docs'))
    ->middleware(config('documentation.ui.middleware', ['web']))
    ->group(function () {
        // Main Swagger UI
        Route::get('/', function (DocumentationGenerator $generator) {
            return response()->view('documentation::swagger', [
                'spec' => $generator->generateRest(),
                'graphql' => $generator->generateGraphql(),
            ]);
        })->name('docs.index');

        // Redoc UI
        Route::get('/redoc', function (DocumentationGenerator $generator) {
            return response()->view('documentation::redoc', [
                'spec' => $generator->generateRest(),
            ]);
        })->name('docs.redoc');

        // GraphQL documentation
        Route::get('/graphql', function (DocumentationGenerator $generator) {
            return response()->view('documentation::graphql', [
                'graphql' => $generator->generateGraphql(),
            ]);
        })->name('docs.graphql');

        // Raw OpenAPI spec
        Route::get('/openapi.json', function (DocumentationGenerator $generator) {
            return response()->json($generator->generateRest())
                ->header('Content-Type', 'application/json');
        })->name('docs.openapi.json');

        // Raw GraphQL SDL
        Route::get('/graphql/schema.graphql', function (DocumentationGenerator $generator) {
            return response($generator->generateGraphql()->schemaSdl)
                ->header('Content-Type', 'text/plain');
        })->name('docs.graphql.sdl');

        // GraphQL introspection
        Route::get('/graphql/introspection', function (DocumentationGenerator $generator) {
            return response()->json($generator->generateGraphql()->introspection);
        })->name('docs.graphql.introspection');
    });