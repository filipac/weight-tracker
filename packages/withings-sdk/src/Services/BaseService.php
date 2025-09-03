<?php

declare(strict_types=1);

namespace Filipac\Withings\Services;

use Filipac\Withings\Client\WithingsClient;

abstract class BaseService
{
    protected WithingsClient $client;

    public function __construct(WithingsClient $client)
    {
        $this->client = $client;
    }
}
