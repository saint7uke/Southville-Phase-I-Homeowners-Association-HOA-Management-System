<?php

declare(strict_types=1);

namespace App\Services\Sms;

final class SmsGatewayException extends \RuntimeException
{
    public function __construct(public readonly string $failure, public readonly bool $definitive = false, public readonly bool $retryable = false)
    {
        parent::__construct('SMS gateway request could not be completed.');
    }
}
