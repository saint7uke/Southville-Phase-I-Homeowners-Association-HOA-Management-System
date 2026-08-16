<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\DuesSetting;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecordPayment
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, User $recorder): Payment
    {
        return DB::transaction(function () use ($data, $recorder): Payment {
            $dues = DuesSetting::query()->findOrFail($data['dues_setting_id']);
            $penalty = (float) ($data['penalty'] ?? 0);
            $balance = max(0, (float) $dues->amount + $penalty - (float) $data['amount_paid']);

            return Payment::query()->create([
                ...$data,
                'or_number' => 'OR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'balance' => $balance,
                'status' => $balance > 0 ? 'Partial' : 'Paid',
                'recorded_by' => $recorder->id,
            ]);
        });
    }
}
