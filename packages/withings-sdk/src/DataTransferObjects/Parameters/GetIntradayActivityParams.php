<?php

declare(strict_types=1);

namespace Filipac\Withings\DataTransferObjects\Parameters;

use Filipac\Withings\Enums\ApiAction;
use Filipac\Withings\Enums\IntradayActivityField;

readonly class GetIntradayActivityParams
{
    public function __construct(
        public ?int $startdate = null,
        public ?int $enddate = null,
        /** @var IntradayActivityField[]|null */
        public ?array $data_fields = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'action' => ApiAction::GET_INTRADAY_ACTIVITY->value,
            'startdate' => $this->startdate,
            'enddate' => $this->enddate,
            'data_fields' => $this->data_fields ? implode(',', array_map(fn ($field) => $field->value, $this->data_fields)) : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Create params with basic intraday fields
     */
    public static function withBasicFields(?int $startdate = null, ?int $enddate = null): self
    {
        return new self(
            startdate: $startdate,
            enddate: $enddate,
            data_fields: [
                IntradayActivityField::STEPS,
                IntradayActivityField::CALORIES,
                IntradayActivityField::HEART_RATE,
            ]
        );
    }

    /**
     * Create params for swimming activities
     */
    public static function forSwimming(?int $startdate = null, ?int $enddate = null): self
    {
        return new self(
            startdate: $startdate,
            enddate: $enddate,
            data_fields: [
                IntradayActivityField::STROKE,
                IntradayActivityField::POOL_LAP,
                IntradayActivityField::DURATION,
            ]
        );
    }
}
