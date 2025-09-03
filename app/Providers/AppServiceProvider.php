<?php

namespace App\Providers;

use App\Socialite\WithingsOAuth2Provider;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

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
        // Register the Withings OAuth2 provider
        Socialite::extend('withings', function ($app) {
            $config = $app['config']['services.withings'];

            return new WithingsOAuth2Provider(
                $app['request'],
                $config['client_id'],
                $config['client_secret'],
                $config['redirect']
            );
        });

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
