<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// Public Guest Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/reviews', [ReviewController::class, 'index']);
Route::post('/reviews', [ReviewController::class, 'store']);

// Authenticated Routes
Route::middleware('auth.token')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
});
