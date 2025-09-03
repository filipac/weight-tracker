<?php

declare(strict_types=1);

namespace Filipac\Withings\DataTransferObjects\Parameters;

use Filipac\Withings\Enums\ApiAction;
use Filipac\Withings\Enums\MeasurementType;

readonly class GetMeasuresParams
{
    public function __construct(
        public ?MeasurementType $meastype = null,
        public ?int $startdate = null,
        public ?int $enddate = null,
        public ?int $lastupdate = null,
        public ?int $offset = null,
        public ?int $category = null,
        /** @var MeasurementType[]|null */
        public ?array $meastypes = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'action' => ApiAction::GET_MEASURES->value,
            'meastype' => $this->meastype?->value,
            'meastypes' => $this->meastypes ? implode(',', array_map(fn ($type) => $type->value, $this->meastypes)) : null,
            'startdate' => $this->startdate,
            'enddate' => $this->enddate,
            'lastupdate' => $this->lastupdate,
            'offset' => $this->offset,
            'category' => $this->category,
        ], fn ($value) => $value !== null);
    }

    /**
     * Create params for weight measurements
     */
    public static function forWeight(?int $startdate = null, ?int $enddate = null): self
    {
        return new self(
            meastype: MeasurementType::WEIGHT,
            startdate: $startdate,
            enddate: $enddate
        );
    }

    /**
     * Create params for body composition measurements
     */
    public static function forBodyComposition(?int $startdate = null, ?int $enddate = null): self
    {
        return new self(
            meastypes: MeasurementType::getBodyCompositionTypes(),
            startdate: $startdate,
            enddate: $enddate
        );
    }

    /**
     * Create params for vital signs measurements
     */
    public static function forVitalSigns(?int $startdate = null, ?int $enddate = null): self
    {
        return new self(
            meastypes: MeasurementType::getVitalSignsTypes(),
            startdate: $startdate,
            enddate: $enddate
        );
    }
}
