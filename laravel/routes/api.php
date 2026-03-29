<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminServerController;
use App\Http\Controllers\Api\AdminServerImportController;
use App\Http\Controllers\Api\DeployMigrationController;
use App\Http\Controllers\Api\AllianceStatsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InactiveFinderController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\TribeController;
use App\Http\Controllers\Api\UserStatsController;
use App\Http\Controllers\Api\VillageStatsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/run_migrate', [DeployMigrationController::class, 'run'])
    ->middleware('throttle:12,1');

Route::get('/servers', [ServerController::class, 'index']);
Route::get('/dashboard/servers', [DashboardController::class, 'serverSummaries']);
Route::get('/tribes', [TribeController::class, 'index']);
Route::get('/village-stats', [VillageStatsController::class, 'index']);
Route::get('/user-stats', [UserStatsController::class, 'index']);
Route::get('/alliance-stats', [AllianceStatsController::class, 'index']);
Route::get('/inactive-finder', [InactiveFinderController::class, 'index']);

Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware(['admin.token'])->prefix('admin')->group(function () {
    Route::post('logout', [AdminAuthController::class, 'logout']);
    Route::get('me', [AdminAuthController::class, 'me']);
    Route::get('servers', [AdminServerController::class, 'index']);
    Route::post('servers', [AdminServerController::class, 'store']);
    Route::put('servers/{server}', [AdminServerController::class, 'update']);
    Route::post('servers/{server}/import', [AdminServerImportController::class, 'stream']);
    Route::post('servers/{server}/import-upload', [AdminServerImportController::class, 'streamUpload']);
});
