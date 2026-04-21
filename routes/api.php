<?php

use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\ArtistImageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/artists', [ArtistController::class, 'index']);
    Route::get('/artists/{id}', [ArtistController::class, 'show']);
    Route::post('/artists', [ArtistController::class, 'store']);
    Route::put('/artists/{id}', [ArtistController::class, 'update']);

    Route::post('/artists/{id}/images', [ArtistImageController::class, 'store']);
    Route::delete('/images/{id}', [ArtistImageController::class, 'destroy']);
    Route::patch('/images/{id}/main', [ArtistImageController::class, 'setMain']);

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/artists/{id}/reviews', [ReviewController::class, 'index']);

    Route::post('/artists/{id}/favorite', [FavoriteController::class, 'toggle']);
    Route::get('/favorites', [FavoriteController::class, 'index']);

});


