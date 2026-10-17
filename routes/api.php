<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClassificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Routes de classification IA
Route::prefix('classification')->group(function () {
    Route::post('/predict', [ClassificationController::class, 'classify']);
    Route::get('/health', [ClassificationController::class, 'health']);
});
