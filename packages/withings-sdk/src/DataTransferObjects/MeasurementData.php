<?php

namespace Filipac\Withings\DataTransferObjects;

use Filipac\Withings\Enums\MeasurementType;
use Illuminate\Support\Collection;

class MeasurementData
{
    public function __construct(
        public int $status = 0,
        public array $measuregrps = [],
        public ?int $offset = null,
        public bool $more = false,
        public string|int|null $timezone = null,
        array $data = []
    ) {
        if (! empty($data)) {
            $this->status = $data['status'] ?? 0;
            $this->measuregrps = $data['body']['measuregrps'] ?? [];
            $this->offset = $data['body']['offset'] ?? null;
            $this->more = (bool) ($data['body']['more'] ?? false);
            $this->timezone = $data['body']['timezone'] ?? null;
        }
    }

    /**
     * Get all measurements as a Collection of Measurement objects
     */
    public function getMeasurements(): Collection
    {
        $measurements = [];

        foreach ($this->measuregrps as $group) {
            foreach ($group['measures'] as $measure) {
                $measurements[] = new Measurement(
                    type: $measure['type'],
                    value: $measure['value'],
                    unit: $measure['unit'],
                    date: $group['date'],
                    grpid: $group['grpid'],
                    category: $group['category'] ?? null
                );
            }
        }

        return collect($measurements);
    }

    /**
     * Get measurements filtered by type
     */
    public function getMeasurementsByType(MeasurementType $type): Collection
    {
        return $this->getMeasurements()
            ->filter(fn (Measurement $measurement) => $measurement->type === $type);
    }

    /**
     * Get weight measurements
     */
    public function getWeightMeasurements(): Collection
    {
        return $this->getMeasurementsByType(MeasurementType::WEIGHT);
    }

    /**
     * Get height measurements
     */
    public function getHeightMeasurements(): Collection
    {
        return $this->getMeasurementsByType(MeasurementType::HEIGHT);
    }

    /**
     * Get latest measurement by type
     */
    public function getLatestMeasurement(MeasurementType $type): ?Measurement
    {
        return $this->getMeasurementsByType($type)
            ->sortByDesc('date')
            ->first();
    }
}
