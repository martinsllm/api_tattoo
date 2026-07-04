<?php

namespace App\Providers;

use App\Models\ArtistImage;
use App\Models\ArtistProfile;
use App\Models\Review;
use App\Policies\ArtistImagePolicy;
use App\Policies\ArtistProfilePolicy;
use App\Policies\ReviewPolicy;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Model::preventLazyLoading(! $this->app->environment('production'));

        Gate::policy(ArtistProfile::class, ArtistProfilePolicy::class);
        Gate::policy(ArtistImage::class, ArtistImagePolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);

        Scramble::configure()
            ->preferPatchMethod()
            ->expose(
                ui: 'docs',
                document: 'docs.json',
            );

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($email),
            ];
        });

    }
}
