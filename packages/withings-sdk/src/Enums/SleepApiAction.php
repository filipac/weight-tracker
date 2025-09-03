<?php

declare(strict_types=1);

namespace Filipac\Withings\Enums;

enum SleepApiAction: string
{
    case GET = 'get';
    case GET_SUMMARY = 'getsummary';
}
