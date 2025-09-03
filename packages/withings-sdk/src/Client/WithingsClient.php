<?php

namespace Filipac\Withings\Client;

use Filipac\Withings\Exceptions\WithingsException;
use Filipac\Withings\Services\DropshipmentService;
use Filipac\Withings\Services\HeartService;
use Filipac\Withings\Services\MeasureService;
use Filipac\Withings\Services\NotificationService;
use Filipac\Withings\Services\OAuth2Service;
use Filipac\Withings\Services\SleepService;
use Filipac\Withings\Services\StethoService;
use Filipac\Withings\Services\UserService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class WithingsClient
{
    private Client $httpClient;

    private string $accessToken;

    private string $refreshToken;

    private string $clientId;

    private string $clientSecret;

    private string $baseUrl;

    public function __construct(
        ?string $accessToken = null,
        ?string $refreshToken = null,
        ?string $clientId = null,
        ?string $clientSecret = null,
        string $baseUrl = 'https://wbsapi.withings.net'
    ) {
        $this->accessToken = $accessToken ?? '';
        $this->refreshToken = $refreshToken ?? '';
        $this->clientId = $clientId ?? '';
        $this->clientSecret = $clientSecret ?? '';
        $this->baseUrl = $baseUrl;

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Filipac/Withings-SDK/1.0',
            ],
        ]);
    }

    public function measures(): MeasureService
    {
        return new MeasureService($this);
    }

    public function heart(): HeartService
    {
        return new HeartService($this);
    }

    public function sleep(): SleepService
    {
        return new SleepService($this);
    }

    public function stetho(): StethoService
    {
        return new StethoService($this);
    }

    public function user(): UserService
    {
        return new UserService($this);
    }

    public function oauth2(): OAuth2Service
    {
        return new OAuth2Service($this);
    }

    public function notifications(): NotificationService
    {
        return new NotificationService($this);
    }

    public function dropshipment(): DropshipmentService
    {
        return new DropshipmentService($this);
    }

    public function get(string $endpoint, array $params = []): array
    {
        try {
            $response = $this->httpClient->get($endpoint, [
                'query' => $params,
                'headers' => $this->getAuthHeaders(),
            ]);

            return $this->handleResponse($response);
        } catch (GuzzleException $e) {
            throw new WithingsException('Request failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    public function post(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->httpClient->post($endpoint, [
                'form_params' => $data,
                'headers' => $this->getAuthHeaders(),
            ]);

            return $this->handleResponse($response);
        } catch (GuzzleException $e) {
            throw new WithingsException('Request failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function setRefreshToken(string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * Check if the client is properly configured with required credentials
     */
    public function isConfigured(): bool
    {
        return ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    private function getAuthHeaders(): array
    {
        if (empty($this->accessToken)) {
            return [];
        }

        return [
            'Authorization' => 'Bearer '.$this->accessToken,
        ];
    }

    private function handleResponse(ResponseInterface $response): array
    {
        $data = json_decode($response->getBody()->getContents(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WithingsException('Invalid JSON response: '.json_last_error_msg());
        }

        // Withings API returns status in the response body
        if (isset($data['status']) && $data['status'] !== 0) {
            $errorMessage = $this->getErrorMessage($data['status']);
            throw new WithingsException("Withings API Error: {$errorMessage} (Status: {$data['status']})");
        }

        return $data;
    }

    private function getErrorMessage(int $status): string
    {
        $errors = [
            100 => 'The operation was successful',
            247 => 'The userid provided is absent, or incorrect',
            250 => 'The provided userid and/or Oauth credentials do not match',
            286 => 'No such subscription could be deleted',
            293 => 'The callback URL is either absent or incorrect',
            294 => 'No such subscription can be edited',
            295 => 'Subscription already exists',
            401 => 'Unauthorized',
            500 => 'An unknown error occurred',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
            601 => 'Too Many Requests',
        ];

        return $errors[$status] ?? "Unknown error (Status: $status)";
    }
}
