<?php

use Filipac\Withings\Exceptions\WithingsException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('can create withings exception', function () {
    $exception = new WithingsException('Test error message', 500);

    expect($exception)->toBeInstanceOf(WithingsException::class)
        ->and($exception)->toBeInstanceOf(Exception::class)
        ->and($exception->getMessage())->toBe('Test error message')
        ->and($exception->getCode())->toBe(500);
});

it('throws exception for invalid JSON response', function () {
    $mock = new MockHandler([
        new Response(200, [], 'invalid json'),
        new Response(200, [], 'invalid json'), // Add second response for second call
    ]);
    $client = createMockClient($mock);

    expect(fn () => $client->get('/test'))
        ->toThrow(WithingsException::class)
        ->and(fn () => $client->get('/test'))
        ->toThrow(WithingsException::class, 'Invalid JSON response:');
});

it('throws exception for API error status codes', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'status' => 401,
            'error' => 'Unauthorized access',
        ])),
        new Response(200, [], json_encode([
            'status' => 401,
            'error' => 'Unauthorized access',
        ])),
    ]);
    $client = createMockClient($mock);

    expect(fn () => $client->get('/test'))
        ->toThrow(WithingsException::class)
        ->and(fn () => $client->get('/test'))
        ->toThrow(WithingsException::class, 'Withings API Error: Unauthorized (Status: 401)'); // Fix expected message
});

it('throws exception for unknown error status codes', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'status' => 999,
            'error' => 'Unknown error',
        ])),
    ]);
    $client = createMockClient($mock);

    expect(fn () => $client->get('/test'))
        ->toThrow(WithingsException::class, 'Unknown error (Status: 999)');
});

it('throws exception for HTTP errors', function () {
    $request = new Request('GET', '/test');
    $mock = new MockHandler([
        new RequestException('Server error', $request, new Response(500)),
    ]);
    $client = createMockClient($mock);

    expect(fn () => $client->get('/test'))
        ->toThrow(WithingsException::class, 'Request failed: Server error');
});

it('includes proper error codes from known withings API errors', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'status' => 247,
        ])),
    ]);
    $client = createMockClient($mock);

    expect(fn () => $client->get('/test'))
        ->toThrow(WithingsException::class, 'Withings API Error: The userid provided is absent, or incorrect (Status: 247)');
});
