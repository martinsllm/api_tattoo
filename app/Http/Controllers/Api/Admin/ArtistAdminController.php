<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;

class ArtistAdminController extends Controller
{
    public function deactivate($id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $artist->update(['is_active' => false]);

        return ApiResponse::success(null, 'Artist deactivated successfully');
    }

    public function activate($id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $artist->update(['is_active' => true]);

        return ApiResponse::success(null, 'Artist activated successfully');
    }
}
