<?php

declare(strict_types=1);

namespace Filipac\Withings;

use Filipac\Withings\Client\WithingsClient;
use Illuminate\Support\ServiceProvider;

class WithingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/withings.php', 'withings');

        $this->app->singleton(WithingsClient::class, function ($app) {
            return new WithingsClient(
                accessToken: config('withings.access_token'),
                refreshToken: config('withings.refresh_token'),
                clientId: config('withings.client_id'),
                clientSecret: config('withings.client_secret'),
                baseUrl: config('withings.base_url', 'https://wbsapi.withings.net')
            );
        });

        $this->app->alias(WithingsClient::class, 'withings');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/withings.php' => config_path('withings.php'),
            ], 'withings-config');
        }
    }
}
