<?php

declare(strict_types=1);

use App\Http\Controllers\DeploymentWebhookController;
use Illuminate\Support\Facades\Route;

// Inbound Git-provider webhooks - authenticated by the per-website token in
// the URL (plus an HMAC signature when the provider sends one), not Sanctum.
// See docs/API.md and App\Http\Controllers\DeploymentWebhookController.
Route::post('/webhooks/deploy/{webhookToken}', DeploymentWebhookController::class)
    ->name('webhooks.deploy');
