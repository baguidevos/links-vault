<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContextController;
use App\Http\Controllers\Api\LinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes for the Links Vault Chrome Extension and API integrations.
|
*/

// Public Authentication / Token Generation
Route::post('/auth/token', [AuthController::class, 'issueToken'])->name('api.auth.token');

// Authenticated Routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/auth/revoke', [AuthController::class, 'revokeToken'])->name('api.auth.revoke');

    // Context (Teams, Categories, Tags)
    Route::get('/context', [ContextController::class, 'index'])->name('api.context');

    // Links Management
    Route::post('/links/preview', [LinkController::class, 'preview'])->name('api.links.preview');
    Route::post('/links', [LinkController::class, 'store'])->name('api.links.store');
});
