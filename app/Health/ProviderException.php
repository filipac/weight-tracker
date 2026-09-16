<?php

namespace App\Health;

class ProviderException extends \RuntimeException
{
    public function __construct(public readonly string $state, string $message)
    {
        parent::__construct($message);
    }
}
