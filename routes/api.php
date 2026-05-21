<?php

use App\Http\Controllers\Api\Admin\ArtistAdminController;
use App\Http\Controllers\Api\Admin\ReviewAdminController;
use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\ArtistImageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StyleController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
});

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/artists', [ArtistController::class, 'index'])->name('artist.index');
    Route::get('/artists/{id}', [ArtistController::class, 'show'])->name('artist.show');
    Route::get('/styles', [StyleController::class, 'index'])->name('style.index');
    Route::get('/tags', [TagController::class, 'index'])->name('tag.index');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    Route::patch('/me', [AuthController::class, 'updateProfile'])->name('auth.update-profile');

    Route::post('/artists', [ArtistController::class, 'store'])->name('artist.store');
    Route::patch('/artists/{id}', [ArtistController::class, 'update'])->name('artist.update');
    Route::patch('/artists/{id}/deactivate', [ArtistController::class, 'deactivate'])->name('artist.deactivate');
    Route::patch('/artists/{id}/activate', [ArtistController::class, 'activate'])->name('artist.activate');

    Route::post('/artists/{id}/images', [ArtistImageController::class, 'store'])->name('artist.image.store');
    Route::delete('/images/{id}', [ArtistImageController::class, 'destroy'])->name('artist.image.destroy');
    Route::patch('/images/{id}/main', [ArtistImageController::class, 'setMain'])->name('artist.image.set-main');

    Route::post('/reviews', [ReviewController::class, 'store'])->name('review.store');
    Route::get('/artists/{id}/reviews', [ReviewController::class, 'index'])->name('artist.review.index');

    Route::post('/artists/{id}/favorite', [FavoriteController::class, 'toggle'])->name('artist.favorite.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorite.index');

});

Route::middleware('auth:sanctum', 'role:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::patch('/artists/{id}/deactivate', [ArtistAdminController::class, 'deactivate'])->name('artist.deactivate');
    Route::patch('/artists/{id}/activate', [ArtistAdminController::class, 'activate'])->name('artist.activate');
    Route::delete('/reviews/{id}', [ReviewAdminController::class, 'destroy'])->name('review.destroy');
});
