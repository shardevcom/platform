<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shardevcom\Communications\Rest\Http\Controllers\AdminAlertController;
use Shardevcom\Communications\Rest\Http\Controllers\EventController;
use Shardevcom\Communications\Rest\Http\Controllers\MessageController;
use Shardevcom\Communications\Rest\Http\Controllers\TemplateController;

Route::middleware(['auth:'.$apiGuard, 'account.active'])
    ->prefix('communications/admin')
    ->group(function () use ($guard): void {
        Route::get('events', [EventController::class, 'index'])
            ->defaults('guard', $guard)
            ->name('communications.admin.events.index.'.$guard);

        Route::put('events/{eventKey}/channels', [EventController::class, 'channels'])
            ->defaults('guard', $guard)
            ->name('communications.admin.events.channels.'.$guard);

        Route::get('events/{eventKey}/templates', [TemplateController::class, 'index'])
            ->defaults('guard', $guard)
            ->name('communications.admin.templates.index.'.$guard);

        Route::put('events/{eventKey}/templates', [TemplateController::class, 'upsert'])
            ->defaults('guard', $guard)
            ->name('communications.admin.templates.upsert.'.$guard);

        Route::get('messages', [MessageController::class, 'index'])
            ->defaults('guard', $guard)
            ->name('communications.admin.messages.index.'.$guard);

        Route::post('alerts', [AdminAlertController::class, 'store'])
            ->defaults('guard', $guard)
            ->name('communications.admin.alerts.store.'.$guard);
    });
