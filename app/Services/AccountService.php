<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function __construct(
        private readonly ArtistImageThumbnailService $thumbnailService,
        private readonly Filesystem $disk,
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

        $this->disk->delete($paths);

    }
}
