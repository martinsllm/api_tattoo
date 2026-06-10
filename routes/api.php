<?php

use App\Http\Controllers\Api\Admin\ArtistAdminController;
use App\Http\Controllers\Api\Admin\ReviewAdminController;
use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\ArtistImageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StyleController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

// API health check endpoint
Route::get('/health', HealthCheckController::class)->name('health.check');

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

Route::middleware(['signed', 'throttle:6,1'])->group(function () {
    Route::get('/email/verify/{id}/{hash}/{token}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify');

    Route::get('/email/verify-change/{id}/{hash}/{token}', [EmailVerificationController::class, 'verifyChange'])
        ->name('verification.verify-change');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/email/resend-verification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('email.resend-verification');

    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    Route::patch('/me', [AuthController::class, 'updateProfile'])->name('auth.update-profile');
    Route::delete('/email/cancel-pending-email', [AuthController::class, 'cancelPendingEmail'])
        ->name('auth.cancel-pending-email');

    Route::post('/artists', [ArtistController::class, 'store'])->name('artist.store');
    Route::patch('/artists/{id}', [ArtistController::class, 'update'])->name('artist.update');
    Route::patch('/artists/{id}/deactivate', [ArtistController::class, 'deactivate'])->name('artist.deactivate');
    Route::patch('/artists/{id}/activate', [ArtistController::class, 'activate'])->name('artist.activate');

    Route::post('/artists/{id}/images', [ArtistImageController::class, 'store'])->name('artist.image.store');
    Route::delete('/images/{id}', [ArtistImageController::class, 'destroy'])->name('artist.image.destroy');
    Route::patch('/images/{id}/main', [ArtistImageController::class, 'setMain'])->name('artist.image.set-main');

    Route::post('/reviews', [ReviewController::class, 'store'])->name('review.store');
    Route::get('/artists/{id}/reviews', [ReviewController::class, 'index'])->name('artist.review.index');
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy'])->name('review.destroy');

    Route::post('/artists/{id}/favorite', [FavoriteController::class, 'toggle'])->name('artist.favorite.toggle');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorite.index');

});

Route::middleware('auth:sanctum', 'role:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::patch('/artists/{id}/deactivate', [ArtistAdminController::class, 'deactivate'])->name('artist.deactivate');
    Route::patch('/artists/{id}/activate', [ArtistAdminController::class, 'activate'])->name('artist.activate');
    Route::delete('/reviews/{id}', [ReviewAdminController::class, 'destroy'])->name('review.destroy');
});
