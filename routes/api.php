<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OAuthClientApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// OAuth Client Management API
Route::prefix('oauth-clients')->name('api.oauth-clients.')->group(function () {
    Route::get('/', [OAuthClientApiController::class, 'index'])->name('index');
    Route::post('/', [OAuthClientApiController::class, 'store'])->name('store');
    Route::get('/stats', [OAuthClientApiController::class, 'stats'])->name('stats');
    Route::get('/attention', [OAuthClientApiController::class, 'attention'])->name('attention');
    Route::post('/batch-health-check', [OAuthClientApiController::class, 'batchHealthCheck'])->name('batch-health-check');
    
    Route::prefix('{client}')->group(function () {
        Route::get('/', [OAuthClientApiController::class, 'show'])->name('show');
        Route::put('/', [OAuthClientApiController::class, 'update'])->name('update');
        Route::delete('/', [OAuthClientApiController::class, 'destroy'])->name('destroy');
        Route::post('/toggle-status', [OAuthClientApiController::class, 'toggleStatus'])->name('toggle-status');
        Route::post('/toggle-maintenance', [OAuthClientApiController::class, 'toggleMaintenance'])->name('toggle-maintenance');
        Route::post('/health-check', [OAuthClientApiController::class, 'healthCheck'])->name('health-check');
        Route::get('/analytics', [OAuthClientApiController::class, 'analytics'])->name('analytics');
        Route::get('/events', [OAuthClientApiController::class, 'events'])->name('events');
        Route::get('/usage', [OAuthClientApiController::class, 'usage'])->name('usage');
    });
});