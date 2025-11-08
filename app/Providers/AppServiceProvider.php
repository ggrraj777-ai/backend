<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (!$this->app->runningInConsole()) {
            $shouldForceHttps = request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https';

            if ($shouldForceHttps && $this->app->environment(['production', 'staging'])) {
                URL::forceScheme('https');
            }
        }
        Paginator::useBootstrap();
    }
}
