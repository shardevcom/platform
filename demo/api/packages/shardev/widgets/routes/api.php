<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shardevcom\Widgets\Http\Controllers\WidgetsController;

/**
 * Widgets REST API routes.
 */
Route::get('widgets', [WidgetsController::class, 'index'])
    ->name('widgets.index');