<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CertificateDuesBlocked extends ValidationException
{
    public function __construct(string $field, string $message)
    {
        $validator = Validator::make([], []);
        $validator->errors()->add($field, $message);
        parent::__construct($validator);
    }
}
