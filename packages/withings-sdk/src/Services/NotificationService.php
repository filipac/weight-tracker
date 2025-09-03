<?php

declare(strict_types=1);

namespace Filipac\Withings\Services;

use Filipac\Withings\DataTransferObjects\Parameters\NotificationParams;
use Filipac\Withings\Enums\NotificationApplication;

class NotificationService extends BaseService
{
    /**
     * Subscribe to notifications
     */
    public function subscribe(string $callbackurl, NotificationApplication $appli = NotificationApplication::WEIGHT, ?string $comment = null): array
    {
        $params = NotificationParams::subscribe($callbackurl, $appli, $comment);

        return $this->client->post('/notify', $params->toArray());
    }

    /**
     * Revoke a notification
     */
    public function revoke(string $callbackurl, NotificationApplication $appli = NotificationApplication::WEIGHT): array
    {
        $params = NotificationParams::revoke($callbackurl, $appli);

        return $this->client->post('/notify', $params->toArray());
    }

    /**
     * Get notification information
     */
    public function get(?NotificationApplication $appli = null): array
    {
        $params = NotificationParams::get($appli);

        return $this->client->post('/notify', $params->toArray());
    }

    /**
     * List all notifications
     */
    public function list(): array
    {
        $params = NotificationParams::list();

        return $this->client->post('/notify', $params->toArray());
    }
}
