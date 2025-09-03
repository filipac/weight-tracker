<?php

namespace Filipac\Withings\DataTransferObjects\Parameters;

use Filipac\Withings\Enums\ApiAction;
use Filipac\Withings\Enums\NotificationApplication;

readonly class NotificationParams
{
    public function __construct(
        public ApiAction $action,
        public ?string $callbackurl = null,
        public ?string $comment = null,
        public ?NotificationApplication $appli = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'action' => $this->action->value,
            'callbackurl' => $this->callbackurl,
            'comment' => $this->comment,
            'appli' => $this->appli?->value,
        ], fn ($value) => $value !== null);
    }

    /**
     * Create params to subscribe to notifications
     */
    public static function subscribe(
        string $callbackurl,
        NotificationApplication $appli,
        ?string $comment = null
    ): self {
        return new self(
            action: ApiAction::NOTIFICATION_SUBSCRIBE,
            callbackurl: $callbackurl,
            appli: $appli,
            comment: $comment
        );
    }

    /**
     * Create params to revoke notifications
     */
    public static function revoke(string $callbackurl, NotificationApplication $appli): self
    {
        return new self(
            action: ApiAction::NOTIFICATION_REVOKE,
            callbackurl: $callbackurl,
            appli: $appli
        );
    }

    /**
     * Create params to get notification info
     */
    public static function get(?NotificationApplication $appli = null): self
    {
        return new self(
            action: ApiAction::NOTIFICATION_GET,
            appli: $appli
        );
    }

    /**
     * Create params to list notifications
     */
    public static function list(): self
    {
        return new self(action: ApiAction::NOTIFICATION_LIST);
    }
}
