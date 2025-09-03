<?php

namespace Filipac\Withings\Services;

use Filipac\Withings\DataTransferObjects\Parameters\GetSleepParams;

class SleepService extends BaseService
{
    /**
     * Get sleep summary data
     */
    public function getSummary(?GetSleepParams $params = null): array
    {
        $params = $params ?? GetSleepParams::forSummary();

        return $this->client->post('/v2/sleep', $params->toArray());
    }

    /**
     * Get detailed sleep data
     */
    public function get(?GetSleepParams $params = null): array
    {
        $params = $params ?? GetSleepParams::forDetailedData();

        return $this->client->post('/v2/sleep', $params->toArray());
    }

    /**
     * Get sleep summary for a date range (Y-m-d format)
     */
    public function getSummaryByDateRange(string $startdateymd, string $enddateymd): array
    {
        $params = GetSleepParams::forSummary($startdateymd, $enddateymd);

        return $this->client->post('/v2/sleep', $params->toArray());
    }

    /**
     * Get detailed sleep data for a date range
     */
    public function getByDateRange(int $startdate, int $enddate): array
    {
        $params = GetSleepParams::forDetailedData($startdate, $enddate);

        return $this->client->post('/v2/sleep', $params->toArray());
    }

    /**
     * Get sleep data with specific fields
     */
    public function getSleepWithFields(array $dataFields, ?int $startdate = null, ?int $enddate = null): array
    {
        $params = GetSleepParams::withFields($dataFields, $startdate, $enddate);

        return $this->client->post('/v2/sleep', $params->toArray());
    }
}
