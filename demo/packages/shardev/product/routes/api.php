<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shardevcom\Product\Rest\Http\Controllers\ProductController;

/**
 * Product REST API routes.
 *
 * Versioned under /api/v1. Controllers are thin adapters.
 * Prefix is applied per-platform in ProductServiceProvider.
 */
Route::middleware('api.version')
    ->group(function (): void {
        // Static segments MUST be registered before the apiResource wildcard,
        // otherwise GET /products/{product} swallows /products/select and
        // /products/export.
        // Select endpoint for dropdowns/autocomplete - returns only selectFields
        Route::get('products/select', [ProductController::class, 'select'])
            ->name('products.select');

        // Import/Export endpoints
        Route::post('products/import', [ProductController::class, 'import'])
            ->name('products.import');
        Route::get('products/import/{importId}/status', [ProductController::class, 'importStatus'])
            ->name('products.import.status');
        Route::get('products/import/{importId}/errors', [ProductController::class, 'importErrors'])
            ->name('products.import.errors');
        Route::get('products/export', [ProductController::class, 'export'])
            ->name('products.export');

        Route::apiResource('products', ProductController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy'])
            ->parameters(['products' => 'product']);

        // GET /api/v1/configuration belongs to the configuration package (T-072),
        // not here. It is declared next to its controller once implemented.
    });