<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shardevcom\Configuration\Http\Controllers\ConfigurationController;

/**
 * Configuration REST API routes (T-072).
 *
 * One endpoint over every registered provider; the registry owns resolution
 * and visibility, so adding a provider never adds a route.
 */
Route::get('configuration', [ConfigurationController::class, 'index'])
    ->name('configuration.index');