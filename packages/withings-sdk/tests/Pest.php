<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(Filipac\Withings\Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeWithingsClient', function () {
    return $this->toBeInstanceOf(\Filipac\Withings\Client\WithingsClient::class);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function mockWithingsResponse(array $data = [], int $status = 0): array
{
    return [
        'status' => $status,
        'body' => $data,
    ];
}

function createMockClient(GuzzleHttp\Handler\MockHandler $mock): Filipac\Withings\Client\WithingsClient
{
    $handlerStack = GuzzleHttp\HandlerStack::create($mock);
    $httpClient = new GuzzleHttp\Client(['handler' => $handlerStack]);

    $client = new Filipac\Withings\Client\WithingsClient(
        accessToken: 'test-access-token',
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    // Use the new public method to set the mock HTTP client
    $client->setHttpClient($httpClient);

    return $client;
}
