<?php

namespace Filipac\Withings\DataTransferObjects\Parameters;

use Filipac\Withings\Enums\StethoApiAction;

readonly class GetStethoParams
{
    public function __construct(
        public ?int $signalid = null,
        public ?int $startdate = null,
        public ?int $enddate = null,
        public ?string $source = null, // 'heart' for heart service signals
        public ?bool $includeRaw = null,
        public ?StethoApiAction $action = null,
    ) {}

    public function toArray(): array
    {
        $action = $this->action ?? ($this->signalid ? StethoApiAction::GET : StethoApiAction::LIST);

        return array_filter([
            'action' => $action->value,
            'signalid' => $this->signalid,
            'startdate' => $this->startdate,
            'enddate' => $this->enddate,
            'source' => $this->source,
            'include_raw' => $this->includeRaw ? 1 : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Create params for ECG data from heart service signal
     */
    public static function forHeartSignal(int $signalid): self
    {
        return new self(
            signalid: $signalid,
            source: 'heart',
            action: StethoApiAction::GET
        );
    }

    /**
     * Create params to list ECG signals
     */
    public static function forList(?int $startdate = null, ?int $enddate = null): self
    {
        return new self(
            startdate: $startdate,
            enddate: $enddate,
            action: StethoApiAction::LIST
        );
    }

    /**
     * Create params for detailed ECG analysis
     */
    public static function forAnalysis(int $signalid, bool $includeRaw = false): self
    {
        return new self(
            signalid: $signalid,
            includeRaw: $includeRaw,
            action: StethoApiAction::GET
        );
    }
}
