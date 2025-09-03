<?php

declare(strict_types=1);

namespace Filipac\Withings\Enums;

enum StethoApiAction: string
{
    case GET = 'get';
    case LIST = 'list';
    case ANALYZE = 'analyze';
}
