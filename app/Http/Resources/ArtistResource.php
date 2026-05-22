<?php

namespace App\Http\Resources;

use App\Models\ArtistProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ArtistProfile
 *
 * Requer eager load: user, styles, tags, images.
 */
class ArtistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAuthenticated = $request->user('sanctum') !== null;

        return [
            'id' => $this->id,
            'studio_name' => $this->studio_name,
            'bio' => $this->bio,
            'city' => $this->city,
            'state' => $this->state,

            'location' => [
                'latitude' => $isAuthenticated ? $this->latitude : null,
                'longitude' => $isAuthenticated ? $this->longitude : null,
                'distance' => $this->when(isset($this->distance), fn () => round($this->distance, 2)),
            ],

            'rating' => $this->when(isset($this->reviews_avg_rating), fn () => round($this->reviews_avg_rating, 1)),

            'favorites_count' => $this->when(isset($this->favorites_count) && $this->favorites_count > 0, fn () => $this->favorites_count),

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),

            'styles' => $this->whenLoaded('styles', fn () => $this->styles->pluck('name')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),

            'images' => $this->whenLoaded('images', fn () => ArtistImageResource::collection($this->images)),

            'created_at' => $this->created_at,
        ];
    }
}
