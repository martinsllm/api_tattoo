<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;

class TagAdminController extends Controller
{
    public function store(StoreTagRequest $request)
    {
        $tag = Tag::create($request->validated());
        Cache::forget('tags');

        return ApiResponse::success(new TagResource($tag), 'Tag created successfully', 201);
    }

    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $tag->update($request->validated());
        Cache::forget('tags');

        return ApiResponse::success(new TagResource($tag), 'Tag updated successfully');
    }

    public function destroy(Tag $tag)
    {
        if ($tag->artistProfiles()->exists()) {
            return ApiResponse::error('Tag is associated with artists', 400);
        }
        $tag->deleteOrFail();
        Cache::forget('tags');

        return ApiResponse::success(null, 'Tag deleted successfully');
    }
}
