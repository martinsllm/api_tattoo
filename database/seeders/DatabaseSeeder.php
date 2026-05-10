<?php

namespace Database\Seeders;

use App\Models\Style;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 🔐 Roles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'artist']);
        Role::firstOrCreate(['name' => 'client']);

        // 🎨 Styles
        $styles = [
            'Realismo',
            'Old School',
            'Fine Line',
            'Blackwork',
            'Aquarela',
        ];

        foreach ($styles as $style) {
            Style::firstOrCreate(['name' => $style]);
        }

        // 🏷️ Tags
        $tags = [
            'Colorido',
            'Preto e cinza',
            'Delicado',
            'Fechado',
            'Minimalista',
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['name' => $tag]);
        }
    }
}
