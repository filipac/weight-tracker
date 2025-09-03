<?php

namespace Filipac\Withings\Services;

use Filipac\Withings\DataTransferObjects\MeasurementData;
use Filipac\Withings\DataTransferObjects\Parameters\GetActivityParams;
use Filipac\Withings\DataTransferObjects\Parameters\GetIntradayActivityParams;
use Filipac\Withings\DataTransferObjects\Parameters\GetMeasuresParams;
use Filipac\Withings\DataTransferObjects\Parameters\GetWorkoutsParams;
use Filipac\Withings\Enums\MeasurementType;

class MeasureService extends BaseService
{
    /**
     * Get body measurements (weight, height, fat, etc.)
     */
    public function getMeasures(?GetMeasuresParams $params = null): MeasurementData
    {
        $params = $params ?? new GetMeasuresParams(meastypes: MeasurementType::getAllTypes());
        $response = $this->client->post('/measure', $params->toArray());

        return new MeasurementData(data: $response);
    }

    /**
     * Get weight measurements specifically
     */
    public function getWeight(?int $startdate = null, ?int $enddate = null, ?int $lastupdate = null): MeasurementData
    {
        $params = GetMeasuresParams::forWeight($startdate, $enddate);
        $response = $this->client->post('/measure', $params->toArray());

        return new MeasurementData(data: $response);
    }

    /**
     * Get height measurements
     */
    public function getHeight(?int $startdate = null, ?int $enddate = null): MeasurementData
    {
        $params = new GetMeasuresParams(
            meastype: MeasurementType::HEIGHT,
            startdate: $startdate,
            enddate: $enddate
        );

        $response = $this->client->post('/measure', $params->toArray());

        return new MeasurementData(data: $response);
    }

    /**
     * Get body composition measurements
     */
    public function getBodyComposition(?int $startdate = null, ?int $enddate = null): MeasurementData
    {
        $params = GetMeasuresParams::forBodyComposition($startdate, $enddate);
        $response = $this->client->post('/measure', $params->toArray());

        return new MeasurementData(data: $response);
    }

    /**
     * Get activity measures (steps, distance, calories, etc.)
     */
    public function getActivityMeasures(?GetActivityParams $params = null): array
    {
        $params = $params ?? new GetActivityParams;

        return $this->client->post('/v2/measure', $params->toArray());
    }

    /**
     * Get workouts
     */
    public function getWorkouts(?GetWorkoutsParams $params = null): array
    {
        $params = $params ?? new GetWorkoutsParams;

        return $this->client->post('/v2/measure', $params->toArray());
    }

    /**
     * Get intraday activity (detailed activity throughout the day)
     */
    public function getIntradayActivity(?GetIntradayActivityParams $params = null): array
    {
        $params = $params ?? new GetIntradayActivityParams;

        return $this->client->post('/v2/measure', $params->toArray());
    }

    /**
     * Get all vital signs measurements
     */
    public function getVitalSigns(?int $startdate = null, ?int $enddate = null): MeasurementData
    {
        $params = GetMeasuresParams::forVitalSigns($startdate, $enddate);
        $response = $this->client->post('/measure', $params->toArray());

        return new MeasurementData(data: $response);
    }

    /**
     * Get temperature measurements
     */
    public function getTemperature(?int $startdate = null, ?int $enddate = null): MeasurementData
    {
        $params = new GetMeasuresParams(
            meastypes: MeasurementType::getTemperatureTypes(),
            startdate: $startdate,
            enddate: $enddate
        );

        $response = $this->client->post('/measure', $params->toArray());

        return new MeasurementData(data: $response);
    }
}
