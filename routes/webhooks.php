<?php

use App\Http\Controllers\Webhooks\QueueWebhookController;
use App\Http\Middleware\VerifyQueueSignature;
use Illuminate\Support\Facades\Route;

/*
| Server-to-server webhooks. Loaded without the `web` middleware group:
| no sessions, cookies or CSRF. Each route must authenticate its caller.
*/

Route::post('webhooks/queue', QueueWebhookController::class)
    ->middleware([VerifyQueueSignature::class, 'throttle:600,1'])
    ->name('webhooks.queue');
