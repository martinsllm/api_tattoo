<?php

use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\ArtistImageController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/artists', [ArtistController::class, 'index']);
    Route::get('/artists/{id}', [ArtistController::class, 'show']);
    Route::post('/artists', [ArtistController::class, 'store']);

    Route::post('/artists/{id}/images', [ArtistImageController::class, 'store']);
});


