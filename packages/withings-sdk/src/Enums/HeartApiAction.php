<?php

namespace Filipac\Withings\Enums;

enum HeartApiAction: string
{
    case GET = 'get';
    case LIST = 'list';
}