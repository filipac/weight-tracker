<?php

namespace Filipac\Withings\Enums;

enum SleepApiAction: string
{
    case GET = 'get';
    case GET_SUMMARY = 'getsummary';
}