<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

final class CurrentPropertyAddress
{
    public static function concise(array|Model $source): string
    {
        $data = $source instanceof Model ? $source->getAttributes() : $source;
        $suffix = $data['phase_suffix'] ?? null;

        return __('Phase').' '.($suffix === 'EXPANSION' ? __('Expansion') : (filled($suffix) ? $suffix : __('Unconfirmed')))
            .' · '.__('Block').' '.($data['block'] ?? '—').' · '.__('Lot').' '.($data['lot'] ?? '—');
    }

    public static function display(array|Model $source): string
    {
        $data = $source instanceof Model ? $source->getAttributes() : $source;
        unset($data['street'], $data['house_number']);

        return str_replace('Phase 1 - EXPANSION', 'Phase 1 - '.__('Expansion'), PropertyIdentity::address($data));
    }
}
