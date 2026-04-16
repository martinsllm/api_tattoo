<?php

use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\ArtistImageController;
use Illuminate\Support\Facades\Route;

Route::get('/artists', [ArtistController::class, 'index']);
Route::get('/artists/{id}', [ArtistController::class, 'show']);
Route::post('/artists', [ArtistController::class, 'store']);

Route::post('/artists/{id}/images', [ArtistImageController::class, 'store']);
