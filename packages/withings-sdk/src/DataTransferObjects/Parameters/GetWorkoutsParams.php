<?php

namespace Filipac\Withings\DataTransferObjects\Parameters;

use Filipac\Withings\Enums\ApiAction;

readonly class GetWorkoutsParams
{
    public function __construct(
        public ?int $startdateymd = null,
        public ?int $enddateymd = null,
        public ?int $offset = null,
        public ?int $lastupdate = null,
        public ?array $data_fields = null, // Array of field names
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'action' => ApiAction::GET_WORKOUTS->value,
            'startdateymd' => $this->startdateymd,
            'enddateymd' => $this->enddateymd,
            'offset' => $this->offset,
            'lastupdate' => $this->lastupdate,
            'data_fields' => $this->data_fields ? implode(',', $this->data_fields) : null,
        ], fn($value) => $value !== null);
    }
}