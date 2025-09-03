<?php

declare(strict_types=1);

namespace Filipac\Withings\Services;

use Filipac\Withings\DataTransferObjects\Parameters\GetHeartParams;

class HeartService extends BaseService
{
    /**
     * Get heart rate measurements
     */
    public function getHeartRate(?GetHeartParams $params = null): array
    {
        $params = $params ?? new GetHeartParams;

        return $this->client->post('/v2/heart', $params->toArray());
    }

    /**
     * List heart rate measurements
     */
    public function list(?int $startdate = null, ?int $enddate = null): array
    {
        $params = new GetHeartParams(
            startdate: $startdate,
            enddate: $enddate
        );

        return $this->client->post('/v2/heart', $params->toArray());
    }

    /**
     * Get ECG data by signal ID
     */
    public function getEcg(int $signalid): array
    {
        $params = new GetHeartParams(signalid: $signalid);

        return $this->client->post('/v2/heart', $params->toArray());
    }

    /**
     * Get detailed heart rate measurements with advanced filtering
     */
    public function getDetailed(?int $startdate = null, ?int $enddate = null, ?int $offset = null): array
    {
        $params = new GetHeartParams(
            startdate: $startdate,
            enddate: $enddate,
            offset: $offset
        );

        return $this->client->post('/v2/heart', $params->toArray());
    }
}
