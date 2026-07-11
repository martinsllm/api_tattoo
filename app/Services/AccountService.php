<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountService
{
    public function delete(User $user): void
    {
        $imagePaths = [];

        if ($user->artistProfile && $user->artistProfile->images) {
            $imagePaths = $user->artistProfile->images->pluck('image_url')->toArray();
        }

        DB::transaction(function () use ($user) {

            $user->tokens()->delete();

            $user->artistProfile?->delete();

            $user->delete();
        });

        Storage::disk('public')->delete($imagePaths);

    }
}
