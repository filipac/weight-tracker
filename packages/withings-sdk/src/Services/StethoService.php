<?php

namespace Filipac\Withings\Services;

use Filipac\Withings\DataTransferObjects\Parameters\GetStethoParams;

class StethoService extends BaseService
{
    /**
     * Get ECG data by signal ID from Stetho API
     */
    public function getEcgData(int $signalid): array
    {
        $params = new GetStethoParams(signalid: $signalid);
        return $this->client->post('/v2/stetho', $params->toArray());
    }

    /**
     * Get ECG info from heart service signal ID
     */
    public function getEcgFromHeartSignal(int $signalid): array
    {
        $params = new GetStethoParams(
            signalid: $signalid,
            source: 'heart'
        );
        return $this->client->post('/v2/stetho', $params->toArray());
    }

    /**
     * List available ECG signals with date filtering
     */
    public function listEcgSignals(?int $startdate = null, ?int $enddate = null): array
    {
        $params = new GetStethoParams(
            startdate: $startdate,
            enddate: $enddate
        );
        return $this->client->post('/v2/stetho', $params->toArray());
    }

    /**
     * Get detailed ECG analysis
     */
    public function getEcgAnalysis(int $signalid, ?bool $includeRawData = false): array
    {
        $params = new GetStethoParams(
            signalid: $signalid,
            includeRaw: $includeRawData
        );
        return $this->client->post('/v2/stetho', $params->toArray());
    }
}