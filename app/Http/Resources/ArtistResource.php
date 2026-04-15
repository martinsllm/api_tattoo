<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studio_name' => $this->studio_name,
            'bio' => $this->bio,
            'city' => $this->city,
            'state' => $this->state,

            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'distance' => $this->when(isset($this->distance), round($this->distance, 2)),
            ],

            'rating' => $this->when(isset($this->reviews_avg_rating), round($this->reviews_avg_rating, 1)),

            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],

            'styles' => $this->styles->pluck('name'),
            'tags' => $this->tags->pluck('name'),

            'images' => $this->images->map(function ($img) {
                return [
                    'id' => $img->id,
                    'url' => $img->url,
                ];
            }),

            'created_at' => $this->created_at,
        ];
    }
}
