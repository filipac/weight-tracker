<?php

declare(strict_types=1);

namespace Filipac\Withings\DataTransferObjects;

use Filipac\Withings\Enums\MeasurementCategory;
use Filipac\Withings\Enums\MeasurementType;

readonly class Measurement
{
    public readonly MeasurementType $type;

    public readonly ?MeasurementCategory $category;

    public function __construct(
        MeasurementType|int $type,
        public int $value,
        public int $unit,
        public int $date,
        public int $grpid,
        MeasurementCategory|int|null $category = null
    ) {
        // Convert integers to enums automatically
        $this->type = $type instanceof MeasurementType ? $type : (MeasurementType::tryFrom($type) ?? MeasurementType::WEIGHT);
        $this->category = $category instanceof MeasurementCategory ? $category : ($category !== null ? MeasurementCategory::tryFrom($category) : null);
    }

    /**
     * Get the actual value adjusted for the unit power
     */
    public function getRealValue(): float
    {
        return $this->value * pow(10, $this->unit);
    }

    /**
     * Get measurement type as enum (always returns enum now)
     */
    public function getMeasurementType(): MeasurementType
    {
        return $this->type;
    }

    /**
     * Get human-readable type name
     */
    public function getTypeName(): string
    {
        return $this->type->getName();
    }

    /**
     * Get measurement unit
     */
    public function getUnit(): string
    {
        return $this->type->getUnit();
    }

    /**
     * Get measurement category name
     */
    public function getCategoryName(): ?string
    {
        return $this->category?->getName();
    }

    /**
     * Get measurement date as DateTime
     */
    public function getDateTime(): \DateTime
    {
        return new \DateTime('@'.$this->date);
    }

    /**
     * Check if measurement is a body composition metric
     */
    public function isBodyComposition(): bool
    {
        return in_array($this->type, MeasurementType::getBodyCompositionTypes());
    }

    /**
     * Check if measurement is a vital sign
     */
    public function isVitalSign(): bool
    {
        return in_array($this->type, MeasurementType::getVitalSignsTypes());
    }

    /**
     * Check if measurement is ECG-related
     */
    public function isEcgMeasurement(): bool
    {
        return in_array($this->type, MeasurementType::getEcgTypes());
    }
}
