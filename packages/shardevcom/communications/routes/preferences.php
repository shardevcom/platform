<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shardevcom\Communications\Rest\Http\Controllers\PreferenceController;

Route::middleware(['auth:'.$apiGuard, 'account.active'])
    ->group(function () use ($guard): void {
        Route::get('communications/preferences', [PreferenceController::class, 'show'])
            ->defaults('guard', $guard)
            ->name('communications.preferences.show.'.$guard);

        Route::post('communications/preferences/opt-out', [PreferenceController::class, 'optOut'])
            ->defaults('guard', $guard)
            ->name('communications.preferences.opt-out.'.$guard);

        Route::post('communications/preferences/opt-in', [PreferenceController::class, 'optIn'])
            ->defaults('guard', $guard)
            ->name('communications.preferences.opt-in.'.$guard);
    });
