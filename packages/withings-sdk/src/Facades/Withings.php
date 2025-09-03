<?php

namespace Filipac\Withings\Facades;

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\Services\MeasureService;
use Filipac\Withings\Services\HeartService;
use Filipac\Withings\Services\SleepService;
use Filipac\Withings\Services\UserService;
use Filipac\Withings\Services\OAuth2Service;
use Filipac\Withings\Services\NotificationService;
use Filipac\Withings\Services\DropshipmentService;
use Illuminate\Support\Facades\Facade;

/**
 * Withings API Facade for Laravel
 * 
 * Provides static access to all Withings API services with full IDE completion.
 * 
 * @example Basic Usage:
 * ```php
 * use Filipac\Withings\Facades\Withings;
 * use Filipac\Withings\Enums\MeasurementType;
 * use Filipac\Withings\Enums\NotificationApplication;
 * 
 * // Get weight measurements
 * $measurements = Withings::measures()->getWeight();
 * 
 * // Subscribe to notifications
 * Withings::notifications()->subscribe('https://webhook.url', NotificationApplication::WEIGHT);
 * 
 * // Get user information
 * $userInfo = Withings::user()->getInfo();
 * ```
 * 
 * @method static MeasureService measures() Access measurement and activity data
 * @method static HeartService heart() Access heart rate and ECG data
 * @method static SleepService sleep() Access sleep tracking data
 * @method static UserService user() Access user profile and device information
 * @method static OAuth2Service oauth2() Handle OAuth2 authentication flow
 * @method static NotificationService notifications() Manage webhook notifications
 * @method static DropshipmentService dropshipment() Manage dropshipment orders
 * 
 * @method static array get(string $endpoint, array $params = []) Make GET request to custom endpoint
 * @method static array post(string $endpoint, array $data = []) Make POST request to custom endpoint
 * 
 * @method static WithingsClient setAccessToken(string $accessToken) Set API access token
 * @method static WithingsClient setRefreshToken(string $refreshToken) Set refresh token
 * @method static string getAccessToken() Get current access token
 * @method static string getRefreshToken() Get current refresh token
 * @method static string getClientId() Get client ID
 * @method static string getClientSecret() Get client secret
 * 
 * @see MeasureService For measurement and activity methods
 * @see HeartService For heart rate and ECG methods
 * @see SleepService For sleep tracking methods
 * @see UserService For user profile methods
 * @see NotificationService For webhook management methods
 * @see OAuth2Service For authentication methods
 * @see DropshipmentService For order management methods
 */
class Withings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WithingsClient::class;
    }
}