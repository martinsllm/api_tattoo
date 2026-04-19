<?php

namespace App\Services;

use App\Models\ArtistImage;
use App\Models\ArtistProfile;
use Illuminate\Support\Facades\Auth;

class ArtistImageService
{
    public function multipleUpload($artistId, $files)
    {
        $artist = ArtistProfile::findOrFail($artistId);

        // Verifica se o usuário é o dono do perfil
        if($artist->user_id !== Auth::user()->id) {
            throw new \Exception('Unauthorized');
        }

        $images = [];

        foreach ($files as $file) {
            $image_url = $file->store('artists', 'public');

            $images[] = ArtistImage::create([
                'artist_profile_id' => $artist->id,
                'image_url' => $image_url,
            ]);
        }

        return $images;
    }

    public function delete($imageId, $user)
    {
        $image = ArtistImage::findOrFail($imageId);

        // Verifica se o usuário é o dono do perfil
        if($image->artistProfile->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }

        \Storage::disk('public')->delete($image->image_url);

        $image->delete();

        return true;
    }
}
