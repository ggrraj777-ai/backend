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
        // Force HTTPS on Cloud Run / production so asset() and URL generation use https
        if ($this->app->environment(['production', 'staging']) || request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
        Paginator::useBootstrap();
    }
}
