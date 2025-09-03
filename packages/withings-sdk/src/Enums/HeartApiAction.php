<?php

declare(strict_types=1);

namespace Filipac\Withings\Enums;

enum HeartApiAction: string
{
    case GET = 'get';
    case LIST = 'list';
}
