<?php

namespace App\Services;

use App\Models\ArtistProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArtistService
{
    public function create(array $data): ArtistProfile
    {
        $user = Auth::user();

        $styles = $data['styles'] ?? [];
        $tags = $data['tags'] ?? [];
        unset($data['styles'], $data['tags']);

        if ($user->artistProfile) {
            throw new \DomainException('User already has an artist profile');
        }

        return DB::transaction(function () use ($user, $data, $styles, $tags) {
            $artist = $user->artistProfile()->create($data);

            if (! empty($styles)) {
                $artist->styles()->sync($styles);
            }

            if (! empty($tags)) {
                $artist->tags()->sync($tags);
            }

            $user->syncRoles(['artist']);

            return $artist;
        });
    }

    public function update(ArtistProfile $artist, array $data): ArtistProfile
    {
        $styles = $data['styles'] ?? null;
        $tags = $data['tags'] ?? null;
        unset($data['styles'], $data['tags']);

        return DB::transaction(function () use ($artist, $data, $styles, $tags) {
            $artist->update($data);

            if (! is_null($styles)) {
                $artist->styles()->sync($styles);
            }

            if (! is_null($tags)) {
                $artist->tags()->sync($tags);
            }

            return $artist;
        });
    }

    public function deactivate(ArtistProfile $artist): void
    {
        $artist->update(['is_active' => false]);
    }

    public function activate(ArtistProfile $artist): void
    {
        $artist->update(['is_active' => true]);
    }
}
