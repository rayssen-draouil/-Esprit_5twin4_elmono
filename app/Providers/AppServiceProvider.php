<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Features;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         Features::twoFactorAuthentication([
        'confirm' => true,
    ]);
        Paginator::defaultView('vendor.pagination.aquasecure');
        Paginator::defaultSimpleView('vendor.pagination.aquasecure-simple');
    }
}
