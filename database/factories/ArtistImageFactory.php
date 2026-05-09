<?php

namespace Database\Factories;

use App\Models\ArtistImage;
use App\Models\ArtistProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArtistImage>
 */
class ArtistImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artist_profile_id' => ArtistProfile::factory(),
            'image_url' => 'artists/'.fake()->uuid().'.jpg',
            'thumbnail_url' => null,
            'description' => null,
            'is_main' => false,
        ];
    }

    /**
     * Marca a imagem como principal do artista.
     */
    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_main' => true,
        ]);
    }
}
