<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shardevcom\TwoFactor\Http\Controllers\TwoFactorController;

/**
 * Two-Factor Authentication routes.
 */
Route::group([], function (): void {
    Route::post('enable', [TwoFactorController::class, 'enable'])
        ->name('twofactor.enable');

    Route::post('disable', [TwoFactorController::class, 'disable'])
        ->name('twofactor.disable');

    Route::post('verify', [TwoFactorController::class, 'verify'])
        ->name('twofactor.verify');

    Route::get('qr-code', [TwoFactorController::class, 'qrCode'])
        ->name('twofactor.qrcode');

    Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes'])
        ->name('twofactor.recovery-codes');

    Route::post('regenerate-recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])
        ->name('twofactor.regenerate-recovery-codes');
});