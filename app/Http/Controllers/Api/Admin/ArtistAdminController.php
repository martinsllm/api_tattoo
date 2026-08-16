<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterAdminArtistsRequest;
use App\Http\Resources\ArtistResource;
use App\Models\ArtistProfile;
use App\Models\AuditLog;
use App\Services\ArtistService;

class ArtistAdminController extends Controller
{
    public function __construct(private ArtistService $artistService) {}

    public function index(FilterAdminArtistsRequest $request)
    {
        $query = ArtistProfile::with([
            'user',
            'styles',
            'tags',
        ])
            ->applyFilters($request->validated());

        return ApiResponse::paginate(ArtistResource::collection($query->paginate($request->integer('per_page', 10))), 'Artists retrieved successfully');
    }

    public function deactivate(ArtistProfile $artist)
    {
        $this->artistService->deactivate($artist);

        AuditLog::record('artist.deactivate', $artist);

        return ApiResponse::success(null, 'Artist deactivated successfully');
    }

    public function activate(ArtistProfile $artist)
    {
        $this->artistService->activate($artist);

        AuditLog::record('artist.activate', $artist);

        return ApiResponse::success(null, 'Artist activated successfully');
    }
}
