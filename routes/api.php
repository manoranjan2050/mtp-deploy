<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\DeploymentController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebsiteController;
use App\Http\Controllers\DeploymentWebhookController;
use Illuminate\Support\Facades\Route;

// Inbound Git-provider webhooks - authenticated by the per-website token in
// the URL (plus an HMAC signature when the provider sends one), not Sanctum.
// See docs/API.md and App\Http\Controllers\DeploymentWebhookController.
Route::post('/webhooks/deploy/{webhookToken}', DeploymentWebhookController::class)
    ->name('webhooks.deploy');

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [UserController::class, 'show'])->middleware('ability:profile:read,*');

    Route::get('/auth/tokens', [AuthTokenController::class, 'index'])->middleware('ability:profile:read,*');
    Route::post('/auth/tokens', [AuthTokenController::class, 'store'])->middleware('ability:profile:read,*');
    Route::delete('/auth/tokens/{tokenId}', [AuthTokenController::class, 'destroy'])->middleware('ability:profile:read,*');

    Route::get('/user/sessions', [SessionController::class, 'index'])->middleware('ability:sessions:write,*');
    Route::delete('/user/sessions/{sessionId}', [SessionController::class, 'destroy'])->middleware('ability:sessions:write,*');

    Route::middleware('ability:websites:read,*')->group(function (): void {
        Route::get('/websites', [WebsiteController::class, 'index']);
        Route::get('/websites/{website}', [WebsiteController::class, 'show']);
    });

    Route::middleware('ability:deployments:read,*')->group(function (): void {
        Route::get('/websites/{website}/deployments', [DeploymentController::class, 'index']);
    });

    Route::middleware('ability:websites:write,*')->group(function (): void {
        Route::post('/websites', [WebsiteController::class, 'store']);
        Route::patch('/websites/{website}', [WebsiteController::class, 'update']);
        Route::delete('/websites/{website}', [WebsiteController::class, 'destroy']);
        Route::post('/websites/{website}/suspend', [WebsiteController::class, 'suspend']);
        Route::post('/websites/{website}/clone', [WebsiteController::class, 'clone']);
    });

    Route::middleware(['ability:deployments:write,*', 'throttle:deploy-api'])->group(function (): void {
        Route::post('/websites/{website}/deployments', [DeploymentController::class, 'store']);
        Route::post('/deployments/{deployment}/rollback', [DeploymentController::class, 'rollback']);
    });
});
