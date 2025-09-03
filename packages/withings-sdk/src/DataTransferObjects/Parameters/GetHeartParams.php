<?php

namespace Filipac\Withings\DataTransferObjects\Parameters;

use Filipac\Withings\Enums\HeartApiAction;

readonly class GetHeartParams
{
    public function __construct(
        public ?int $startdate = null,
        public ?int $enddate = null,
        public ?int $offset = null,
        public ?int $lastupdate = null,
        public ?int $signalid = null, // For ECG data
        public ?HeartApiAction $action = null,
    ) {}

    public function toArray(): array
    {
        $action = $this->action ?? ($this->signalid ? HeartApiAction::GET : HeartApiAction::LIST);
        
        return array_filter([
            'action' => $action->value,
            'startdate' => $this->startdate,
            'enddate' => $this->enddate,
            'offset' => $this->offset,
            'lastupdate' => $this->lastupdate,
            'signalid' => $this->signalid,
        ], fn($value) => $value !== null);
    }

    /**
     * Create params for ECG data
     */
    public static function forEcg(int $signalid): self
    {
        return new self(
            signalid: $signalid,
            action: HeartApiAction::GET
        );
    }

    /**
     * Create params to list heart rate measurements
     */
    public static function forList(?int $startdate = null, ?int $enddate = null): self
    {
        return new self(
            startdate: $startdate,
            enddate: $enddate,
            action: HeartApiAction::LIST
        );
    }
}