<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountService
{
    public function __construct(
        private readonly ArtistImageThumbnailService $thumbnailService,
    ) {}

    public function delete(User $user): void
    {
        $paths = [];

        if ($user->artistProfile?->images) {
            $images = $user->artistProfile->images;
            $paths = $images->pluck('image_url')->all();

            foreach ($images as $image) {
                $this->thumbnailService->delete($image);
            }
        }

        DB::transaction(function () use ($user) {

            $user->tokens()->delete();

            $user->artistProfile?->delete();

            $user->delete();
        });

        Storage::disk('public')->delete($paths);

    }
}
