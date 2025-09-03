<?php

namespace Filipac\Withings\Enums;

enum StethoApiAction: string
{
    case GET = 'get';
    case LIST = 'list';
    case ANALYZE = 'analyze';
}
