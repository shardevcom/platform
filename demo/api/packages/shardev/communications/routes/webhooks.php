<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Shardevcom\Communications\Rest\Http\Controllers\WebhookController;

Route::post('{provider}', [WebhookController::class, 'handle'])
    ->where('provider', '[A-Za-z0-9_-]+')
    ->name('communications.webhooks.handle');
