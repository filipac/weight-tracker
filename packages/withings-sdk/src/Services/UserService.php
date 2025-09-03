<?php

declare(strict_types=1);

namespace Filipac\Withings\Services;

class UserService extends BaseService
{
    /**
     * Get user information
     */
    public function getInfo(): array
    {
        return $this->client->post('/v2/user', [
            'action' => 'getbyuserid',
        ]);
    }

    /**
     * Get user devices
     */
    public function getDevices(): array
    {
        return $this->client->post('/v2/user', [
            'action' => 'getdevice',
        ]);
    }

    /**
     * Get user goals
     */
    public function getGoals(): array
    {
        return $this->client->post('/v2/user', [
            'action' => 'getgoals',
        ]);
    }

    /**
     * Get user's timezone
     */
    public function getTimezone(): array
    {
        return $this->client->post('/v2/user', [
            'action' => 'get',
            'fields' => 'timezone',
        ]);
    }

    /**
     * Get user preferences
     */
    public function getPreferences(): array
    {
        return $this->client->post('/v2/user', [
            'action' => 'getprefs',
        ]);
    }
}
