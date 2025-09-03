<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        $creds = cache()->get('withings');
        // dd($creds);
        config([
            'withings.access_token' => $creds['access_token'],
            'withings.refresh_token' => $creds['refresh_token'],
            'withings.client_id' => config('services.withings.client_id'),
            'withings.client_secret' => config('services.withings.client_secret'),
        ]);
    }
}
