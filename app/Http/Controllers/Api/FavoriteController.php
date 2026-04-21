<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FavoriteService;

class FavoriteController extends Controller
{
    public function __construct(private FavoriteService $favoriteService)
    {
        $this->favoriteService = $favoriteService;
    }

    public function toggle($artistId) {
        return $this->favoriteService->toggle($artistId);
    }
}
