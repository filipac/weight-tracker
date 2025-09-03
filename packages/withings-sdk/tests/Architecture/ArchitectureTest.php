<?php

arch('services')
    ->expect('Filipac\Withings\Services')
    ->toExtend('Filipac\Withings\Services\BaseService')
    ->toBeClasses()
    ->toHaveSuffix('Service');

arch('enums')
    ->expect('Filipac\Withings\Enums')
    ->toBeEnums();

arch('data transfer objects')
    ->expect('Filipac\Withings\DataTransferObjects')
    ->toBeClasses()
    ->toImplementNothing();

arch('exceptions')
    ->expect('Filipac\Withings\Exceptions')
    ->toExtend(Exception::class)
    ->toHaveSuffix('Exception');

arch('facades')
    ->expect('Filipac\Withings\Facades')
    ->toExtend('Illuminate\Support\Facades\Facade')
    ->toHaveSuffix('');

arch('no debugging')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'echo'])
    ->not->toBeUsed();

arch('strict types')
    ->expect('Filipac\Withings')
    ->toUseStrictTypes();

arch('no globals')
    ->expect('Filipac\Withings')
    ->not->toUse(['global', '$GLOBALS', 'super globals'])
    ->ignoring('request'); // Allow Laravel's request() helper

arch('security')
    ->expect(['eval', 'exec', 'system', 'shell_exec', 'passthru'])
    ->not->toBeUsed();

arch('naming conventions')
    ->expect('Filipac\Withings\Services')
    ->classes()
    ->toHaveSuffix('Service');

arch('parameter objects')
    ->expect('Filipac\Withings\DataTransferObjects\Parameters')
    ->toBeClasses()
    ->toHaveSuffix('Params');

arch('readonly DTOs')
    ->expect('Filipac\Withings\DataTransferObjects\Measurement')
    ->toBeReadonly();

arch('client is final')
    ->expect('Filipac\Withings\Client\WithingsClient')
    ->toBeClasses();

arch('base service is abstract')
    ->expect('Filipac\Withings\Services\BaseService')
    ->toBeAbstract();
