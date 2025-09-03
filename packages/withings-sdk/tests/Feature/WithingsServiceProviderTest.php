<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\Facades\Withings;

it('registers withings client in service container', function () {
    $client = app(WithingsClient::class);

    expect($client)->toBeWithingsClient();
});

it('can use withings facade', function () {
    expect(Withings::getFacadeRoot())->toBeWithingsClient();
});

it('loads configuration from withings config file', function () {
    expect(config('withings.client_id'))->toBe('test-client-id')
        ->and(config('withings.client_secret'))->toBe('test-client-secret')
        ->and(config('withings.redirect_uri'))->toBe('https://example.com/callback');
});

it('has correct package configuration', function () {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey('Filipac\\Withings\\WithingsServiceProvider');
});
