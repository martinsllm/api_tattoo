<?php

namespace App\Actions;

use App\Models\User;
use App\Services\ArtistImageThumbnailService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

class DeleteUserAccountAction
{
    public function __construct(
        private readonly ArtistImageThumbnailService $thumbnailService,
        private readonly Filesystem $disk,
    ) {}

    public function __invoke(User $user)
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
