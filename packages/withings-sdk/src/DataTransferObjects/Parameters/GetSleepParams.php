<?php

namespace Filipac\Withings\DataTransferObjects\Parameters;

use Filipac\Withings\Enums\SleepApiAction;
use Filipac\Withings\Enums\SleepField;

readonly class GetSleepParams
{
    public function __construct(
        public ?int $startdate = null,
        public ?int $enddate = null,
        public ?string $startdateymd = null, // Y-m-d format string
        public ?string $enddateymd = null,   // Y-m-d format string
        public ?int $lastupdate = null,
        /** @var SleepField[]|null */
        public ?array $data_fields = null,
        public SleepApiAction $action = SleepApiAction::GET_SUMMARY,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'action' => $this->action->value,
            'startdate' => $this->startdate,
            'enddate' => $this->enddate,
            'startdateymd' => $this->startdateymd,
            'enddateymd' => $this->enddateymd,
            'lastupdate' => $this->lastupdate,
            'data_fields' => $this->data_fields ? implode(',', array_map(fn ($field) => $field->value, $this->data_fields)) : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Create params for sleep summary using Y-m-d date format
     */
    public static function forSummary(?string $startdateymd = null, ?string $enddateymd = null): self
    {
        return new self(
            action: SleepApiAction::GET_SUMMARY,
            startdateymd: $startdateymd,
            enddateymd: $enddateymd
        );
    }

    /**
     * Create params for sleep summary using DateTime objects (converted to Y-m-d)
     */
    public static function forSummaryFromDateTime(?\DateTime $startDate = null, ?\DateTime $endDate = null): self
    {
        return new self(
            action: SleepApiAction::GET_SUMMARY,
            startdateymd: $startDate?->format('Y-m-d'),
            enddateymd: $endDate?->format('Y-m-d')
        );
    }

    /**
     * Create params for detailed sleep data
     */
    public static function forDetailedData(?int $startdate = null, ?int $enddate = null): self
    {
        return new self(
            action: SleepApiAction::GET,
            startdate: $startdate,
            enddate: $enddate
        );
    }

    /**
     * Create params with specific sleep fields
     */
    public static function withFields(array $fields, ?int $startdate = null, ?int $enddate = null): self
    {
        return new self(
            action: SleepApiAction::GET,
            startdate: $startdate,
            enddate: $enddate,
            data_fields: $fields
        );
    }
}
