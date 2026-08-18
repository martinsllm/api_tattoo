<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStyleRequest;
use App\Http\Requests\UpdateStyleRequest;
use App\Http\Resources\StyleResource;
use App\Models\Style;
use Illuminate\Support\Facades\Cache;

class StyleAdminController extends Controller
{
    public function store(StoreStyleRequest $request)
    {
        $style = Style::create($request->validated());
        Cache::forget('styles');

        return ApiResponse::success(new StyleResource($style), 'Style created successfully', 201);
    }

    public function update(UpdateStyleRequest $request, Style $style)
    {
        $style->update($request->validated());
        Cache::forget('styles');

        return ApiResponse::success(new StyleResource($style), 'Style updated successfully');
    }

    public function destroy(Style $style)
    {
        if ($style->artistProfiles()->exists()) {
            return ApiResponse::error('Style is associated with artists', 400);
        }
        $style->deleteOrFail();
        Cache::forget('styles');

        return ApiResponse::success(null, 'Style deleted successfully');
    }
}
