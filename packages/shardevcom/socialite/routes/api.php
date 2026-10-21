<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shardevcom\Socialite\Http\Controllers\SocialiteController;

/**
 * Socialite OAuth2 routes.
 */
Route::get('providers', [SocialiteController::class, 'providers'])
    ->name('socialite.providers');

Route::get('{provider}', [SocialiteController::class, 'redirect'])
    ->name('socialite.redirect');

Route::get('{provider}/callback', [SocialiteController::class, 'callback'])
    ->name('socialite.callback');