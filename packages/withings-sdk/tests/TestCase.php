<?php

namespace Filipac\Withings\Tests;

use Filipac\Withings\WithingsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            WithingsServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Withings' => \Filipac\Withings\Facades\Withings::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('withings.client_id', 'test-client-id');
        $app['config']->set('withings.client_secret', 'test-client-secret');
        $app['config']->set('withings.redirect_uri', 'https://example.com/callback');
    }
}
