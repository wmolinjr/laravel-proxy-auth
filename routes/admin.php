<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\OAuthClientController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\Admin\SystemSettingController;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {

    // Dashboard and Analytics
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');

    // User Management
    Route::resource('users', UserController::class);
    Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::delete('users/{user}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');

    // OAuth Client Management
    Route::resource('oauth-clients', OAuthClientController::class);
    Route::post('oauth-clients/{oauthClient}/regenerate-secret', [OAuthClientController::class, 'regenerateSecret'])
        ->name('oauth-clients.regenerate-secret');
    Route::post('oauth-clients/{oauthClient}/revoke-tokens', [OAuthClientController::class, 'revokeTokens'])
        ->name('oauth-clients.revoke-tokens');
    
    // Enhanced OAuth Client Management
    Route::prefix('oauth-clients/{oauthClient}')->name('oauth-clients.')->group(function () {
        Route::post('/toggle-status', [OAuthClientController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/toggle-maintenance', [OAuthClientController::class, 'toggleMaintenance'])->name('toggle-maintenance');
        Route::post('/health-check', [OAuthClientController::class, 'healthCheck'])->name('health-check');
        Route::get('/analytics', [OAuthClientController::class, 'analytics'])->name('analytics');
        Route::get('/events', [OAuthClientController::class, 'events'])->name('events');
        Route::get('/usage', [OAuthClientController::class, 'usage'])->name('usage');
    });

    // Token Management
    Route::resource('tokens', App\Http\Controllers\Admin\TokenController::class)->only(['index', 'show', 'destroy']);
    Route::post('tokens/revoke', [App\Http\Controllers\Admin\TokenController::class, 'revoke'])->name('tokens.revoke');
    Route::post('tokens/revoke-all', [App\Http\Controllers\Admin\TokenController::class, 'revokeAll'])->name('tokens.revoke-all');
    Route::post('tokens/cleanup', [App\Http\Controllers\Admin\TokenController::class, 'cleanup'])->name('tokens.cleanup');

    // Audit Logs
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/export', [AuditLogController::class, 'export'])->name('export');
    });

    // Security Events
    Route::prefix('security-events')->name('security-events.')->group(function () {
        Route::get('/', [SecurityEventController::class, 'index'])->name('index');
        Route::post('/{securityEvent}/resolve', [SecurityEventController::class, 'resolve'])->name('resolve');
    });

    // System Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SystemSettingController::class, 'index'])->name('index');
        Route::post('/update', [SystemSettingController::class, 'update'])->name('update');
    });
});
