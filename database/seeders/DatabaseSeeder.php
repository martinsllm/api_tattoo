<?php

namespace Database\Seeders;

use App\Models\Style;
use App\Models\Tag;
use App\Models\User;
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
        // User::factory(10)->create();

        // 🔐 Roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'artist']);
        Role::create(['name' => 'client']);

        // 🎨 Styles
        $styles = [
            'Realismo',
            'Old School',
            'Fine Line',
            'Blackwork',
            'Aquarela',
        ];

        foreach ($styles as $style) {
            Style::create(['name' => $style]);
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
            Tag::create(['name' => $tag]);
        }
    }
}
