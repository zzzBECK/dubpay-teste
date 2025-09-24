<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});


Route::prefix('webhooks')->group(function () {
    Route::post('/payment/{provider}', [PaymentController::class, 'processWebhook']);
});


Route::middleware('auth:sanctum')->group(function () {
    
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });
    
    
    Route::prefix('payments')->group(function () {
        Route::post('/', [PaymentController::class, 'createPayment']);
        Route::get('/', [PaymentController::class, 'listPayments']);
        Route::get('/{external_id}', [PaymentController::class, 'getPayment']);
    });
});