<?php

use App\Http\Controllers\WeightController;
use App\Http\Controllers\WithingsOAuth2Controller;
use Illuminate\Support\Facades\Route;

Route::get('/', [WeightController::class, 'index'])->name('weight.index');
Route::post('/weight', [WeightController::class, 'store'])->name('weight.store');
Route::delete('/weight/{id}', [WeightController::class, 'destroy'])->name('weight.destroy');
Route::post('/weight/sync', [WeightController::class, 'sync'])->name('weight.sync');
Route::post('/weight/get-from-withings', [WeightController::class, 'getFromWithings'])->name('weight.getFromWithings');

// Goal management routes
Route::post('/goals', [WeightController::class, 'storeGoal'])->name('goals.store');
Route::put('/goals/{id}', [WeightController::class, 'updateGoal'])->name('goals.update');
Route::delete('/goals/{id}', [WeightController::class, 'destroyGoal'])->name('goals.destroy');
Route::post('/goals/recalculate', [WeightController::class, 'recalculateGoals'])->name('goals.recalculate');

// Waist measurement routes
Route::post('/waist', [WeightController::class, 'storeWaist'])->name('waist.store');
Route::delete('/waist/{id}', [WeightController::class, 'destroyWaist'])->name('waist.destroy');

// Withings OAuth2 routes
Route::get('/w', [WithingsOAuth2Controller::class, 'redirect'])->name('auth.withings.redirect');
Route::get('/oauth-callback/withings', [WithingsOAuth2Controller::class, 'callback'])->name('auth.withings.callback');

// Private local health publishing. Providers and credentials never reach the browser.
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/login-oura', [\App\Http\Controllers\Health\OuraController::class, 'redirect'])->name('auth.oura.redirect');
    Route::get(config('health.oura.redirect_path'), [\App\Http\Controllers\Health\OuraController::class, 'callback'])->name('auth.oura.callback');
    Route::post('/health/test-connection', [\App\Http\Controllers\Health\PreviewController::class, 'testConnection']);
    Route::get('/health/status', [\App\Http\Controllers\Health\PreviewController::class, 'status']);
    Route::post('/health/destination', [\App\Http\Controllers\Health\PreviewController::class, 'rememberDestination']);
    Route::post('/health/previews', [\App\Http\Controllers\Health\PreviewController::class, 'start']);
    Route::post('/health/previews/{id}/fetch/{task}', [\App\Http\Controllers\Health\PreviewController::class, 'fetch']);
    Route::post('/health/previews/{id}/prepare', [\App\Http\Controllers\Health\PreviewController::class, 'prepare']);
    Route::post('/health/previews/{id}/publish', [\App\Http\Controllers\Health\PreviewController::class, 'publish']);
    Route::delete('/health/previews/{id}', [\App\Http\Controllers\Health\PreviewController::class, 'cancel']);
});
