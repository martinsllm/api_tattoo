<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MakeUserAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        Role::findOrCreate('artist');
        Role::findOrCreate('admin');
    }

    public function test_promotes_existing_user_to_admin_and_replaces_previous_role(): void
    {
        $user = User::factory()->create(['email' => 'lucas@example.com']);
        $user->assignRole('client');

        $this->artisan('user:make-admin', ['email' => 'lucas@example.com'])
            ->expectsOutputToContain('promovido a admin com sucesso')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('client'));
    }

    public function test_fails_when_user_does_not_exist(): void
    {
        $this->artisan('user:make-admin', ['email' => 'ghost@example.com'])
            ->expectsOutputToContain('não encontrado')
            ->assertFailed();
    }

    public function test_is_idempotent_when_user_is_already_admin(): void
    {
        $user = User::factory()->create(['email' => 'lucas@example.com']);
        $user->assignRole('admin');

        $this->artisan('user:make-admin', ['email' => 'lucas@example.com'])
            ->expectsOutputToContain('já é admin')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->hasRole('admin'));
    }
}
