<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use App\Services\ArtistService;

class ArtistAdminController extends Controller
{
    public function __construct(private ArtistService $artistService) {}

    public function deactivate($id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $this->artistService->deactivate($artist);

        return ApiResponse::success(null, 'Artist deactivated successfully');
    }

    public function activate($id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $this->artistService->activate($artist);

        return ApiResponse::success(null, 'Artist activated successfully');
    }
}
