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

// Withings OAuth2 routes
Route::get('/w', [WithingsOAuth2Controller::class, 'redirect'])->name('auth.withings.redirect');
Route::get('/oauth-callback/withings', [WithingsOAuth2Controller::class, 'callback'])->name('auth.withings.callback');
