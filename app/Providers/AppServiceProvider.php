<?php

namespace App\Providers;

use App\Models\ArtistImage;
use App\Models\ArtistProfile;
use App\Models\Review;
use App\Policies\ArtistImagePolicy;
use App\Policies\ArtistProfilePolicy;
use App\Policies\ReviewPolicy;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ArtistProfile::class, ArtistProfilePolicy::class);
        Gate::policy(ArtistImage::class, ArtistImagePolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);

        Scramble::configure()
            ->preferPatchMethod()
            ->expose(
                ui: 'docs',
                document: 'docs.json',
            );
    }
}
