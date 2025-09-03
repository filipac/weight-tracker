<?php

declare(strict_types=1);

namespace Filipac\Withings\DataTransferObjects\Parameters;

use Filipac\Withings\Enums\ActivityField;
use Filipac\Withings\Enums\ApiAction;

readonly class GetActivityParams
{
    public function __construct(
        public ?int $startdateymd = null,
        public ?int $enddateymd = null,
        public ?int $offset = null,
        public ?int $lastupdate = null,
        /** @var ActivityField[]|null */
        public ?array $data_fields = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'action' => ApiAction::GET_ACTIVITY->value,
            'startdateymd' => $this->startdateymd,
            'enddateymd' => $this->enddateymd,
            'offset' => $this->offset,
            'lastupdate' => $this->lastupdate,
            'data_fields' => $this->data_fields ? implode(',', array_map(fn ($field) => $field->value, $this->data_fields)) : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Create params with basic activity fields
     */
    public static function withBasicFields(?int $startdateymd = null, ?int $enddateymd = null): self
    {
        return new self(
            startdateymd: $startdateymd,
            enddateymd: $enddateymd,
            data_fields: [
                ActivityField::STEPS,
                ActivityField::DISTANCE,
                ActivityField::CALORIES,
            ]
        );
    }

    /**
     * Create params with heart rate fields
     */
    public static function withHeartRateFields(?int $startdateymd = null, ?int $enddateymd = null): self
    {
        return new self(
            startdateymd: $startdateymd,
            enddateymd: $enddateymd,
            data_fields: [
                ActivityField::HR_AVERAGE,
                ActivityField::HR_MIN,
                ActivityField::HR_MAX,
                ActivityField::HR_ZONE_0,
                ActivityField::HR_ZONE_1,
                ActivityField::HR_ZONE_2,
                ActivityField::HR_ZONE_3,
            ]
        );
    }
}
