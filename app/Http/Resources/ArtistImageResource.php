<?php

namespace App\Http\Resources;

use App\Models\ArtistImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ArtistImage
 */
class ArtistImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $originalUrl = asset('storage/'.$this->image_url);

        return [
            'id' => $this->id,
            'url' => $originalUrl,
            'thumbnail_url' => $this->thumbnail_url ? asset('storage/'.$this->thumbnail_url) : $originalUrl,
            'is_main' => $this->is_main,
            'created_at' => $this->created_at,
        ];
    }
}
