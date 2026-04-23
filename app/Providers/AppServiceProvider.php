<?php

namespace App\Providers;

use App\Models\ArtistProfile;
use App\Policies\ArtistProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ArtistProfile::class, ArtistProfilePolicy::class);
    }
}
