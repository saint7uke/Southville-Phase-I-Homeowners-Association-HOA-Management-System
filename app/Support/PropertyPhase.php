<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;

final class PropertyPhase
{
    public static function options(): array
    {
        return ['A' => 'A', 'B' => 'B', 'EXPANSION' => __('Expansion')];
    }

    public static function rules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', Rule::in(array_keys(self::options()))];
    }
}
