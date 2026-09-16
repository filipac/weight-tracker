<?php

namespace App\Health;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BlogClient
{
    public function connection(string $destination): array
    {
        $connection = config('health.blogs.'.$destination);
        if (! in_array($destination, ['local', 'production'], true) || ! is_array($connection)) {
            throw new ProviderException('configuration', 'Unknown blog destination.');
        }
        if (! $connection['username'] || ! $connection['password']) {
            throw new ProviderException('configuration', 'Configure the username and Application Password for this destination.');
        }
        $url = parse_url($connection['url']);
        if (($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
            throw new ProviderException('configuration', 'The blog URL must be a plain HTTPS URL.');
        }

        return $connection;
    }

    public function identity(string $destination): string
    {
        return hash('sha256', json_encode($this->connection($destination), JSON_THROW_ON_ERROR));
    }

    public function test(string $destination): array
    {
        return $this->send($destination, 'get', [], 'health-connection');
    }

    public function read(string $destination, string $topic, string $date): array
    {
        return $this->send($destination, 'get', ['topic' => $topic, 'date' => $date]);
    }

    public function publish(string $destination, array $entry, ?string $revision): array
    {
        return $this->send($destination, 'post', $entry + ['expected_revision' => $revision]);
    }

    /** Read-only preview checks; writes still run individually after confirmation. */
    public function readMany(string $destination, array $entries): array
    {
        $connection = $this->connection($destination);
        $results = [];
        $limit = max(1, min(6, (int) config('health.fetch_concurrency', 4)));
        foreach (array_chunk($entries, $limit, true) as $batch) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($connection, $batch) {
                    foreach ($batch as $key => $entry) {
                        $pool->as($key)->withBasicAuth($connection['username'], $connection['password'])
                            ->acceptJson()->timeout(20)->connectTimeout(5)->withoutRedirecting()
                            ->get(rtrim($connection['url'], '/').'/wp-json/pacurar2020/v1/health-entries', [
                                'topic' => $entry['topic'], 'date' => $entry['date'],
                            ]);
                    }
                });
            } catch (\Throwable) {
                throw new ProviderException('connection', 'The blog could not be reached. Check its HTTPS certificate and connection, then retry.');
            }
            foreach ($responses as $key => $response) {
                $results[$key] = $this->decode($response);
            }
        }

        return $results;
    }

    private function send(string $destination, string $method, array $body, string $endpoint = 'health-entries'): array
    {
        $connection = $this->connection($destination);
        try {
            $response = Http::withBasicAuth($connection['username'], $connection['password'])
                ->acceptJson()->timeout(20)->connectTimeout(5)->withoutRedirecting()
                ->$method(rtrim($connection['url'], '/').'/wp-json/pacurar2020/v1/'.$endpoint, $body);
        } catch (\Throwable) {
            throw new ProviderException('connection', 'The blog could not be reached. Check its HTTPS certificate and connection, then retry.');
        }

        return $this->decode($response);
    }

    private function decode(mixed $response): array
    {
        if (! $response instanceof Response) {
            throw new ProviderException('connection', 'The blog could not be reached. Check its HTTPS certificate and connection, then retry.');
        }
        if ($response->status() === 409) {
            throw new ProviderException('stale', 'This entry changed after preview. Fetch a fresh preview before updating it.');
        }
        if (in_array($response->status(), [401, 403])) {
            throw new ProviderException('authentication', 'The blog rejected this Application Password or publishing permission.');
        }
        if ($response->status() === 404) {
            throw new ProviderException('configuration', 'The health journal endpoint is not installed on this destination.');
        }
        if (! $response->successful() || ! is_array($response->json()) || $response->json('schema_version') !== 1) {
            throw new ProviderException('error', 'The blog returned an unexpected response. Verify the health journal theme setup.');
        }

        return $response->json();
    }
}
