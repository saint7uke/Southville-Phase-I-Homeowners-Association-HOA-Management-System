<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\SmsDelivery;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

final class SmsBudget
{
    public function reserve(iterable $deliveries): bool
    {
        return DB::transaction(function () use ($deliveries): bool {
            $day = now('Asia/Manila')->toDateString();
            $deliveries = collect($deliveries)->sortBy('phone_hash');
            foreach ($deliveries as $delivery) {
                if ($delivery->reserved_on !== null && $delivery->reserved_on !== $day) {
                    $this->release($delivery);
                }
            }
            $needed = $deliveries->filter(fn ($row): bool => $row->reserved_on === null);
            if ($needed->isEmpty()) {
                return true;
            }
            DB::table('sms_daily_usage')->insertOrIgnore(['day' => $day, 'segments' => 0]);
            $usage = DB::table('sms_daily_usage')->where('day', $day)->lockForUpdate()->first();
            if ($usage->segments + $needed->sum('segments') > SystemSetting::current()->sms_daily_segments) {
                return false;
            }
            $notifications = $needed->whereNotIn('type', ['otp', 'test'])->groupBy('phone_hash');
            foreach ($notifications as $hash => $rows) {
                DB::table('sms_phone_daily_usage')->insertOrIgnore(['day' => $day, 'phone_hash' => $hash, 'notifications' => 0]);
                $phone = DB::table('sms_phone_daily_usage')->where('day', $day)->where('phone_hash', $hash)->lockForUpdate()->first();
                if ($phone->notifications + $rows->count() > 3) {
                    return false;
                }
            }
            DB::table('sms_daily_usage')->where('day', $day)->increment('segments', $needed->sum('segments'));
            foreach ($notifications as $hash => $rows) {
                DB::table('sms_phone_daily_usage')->where('day', $day)->where('phone_hash', $hash)->increment('notifications', $rows->count());
            }
            foreach ($needed as $delivery) {
                $delivery->update(['reserved_on' => $day]);
            }

            return true;
        }, 3);
    }

    public function release(SmsDelivery $delivery): void
    {
        if ($delivery->reserved_on === null) {
            return;
        }
        // Caller owns the batch/delivery. Always daily counter before phone counter.
        DB::table('sms_daily_usage')->where('day', $delivery->reserved_on)->lockForUpdate()->first();
        DB::table('sms_daily_usage')->where('day', $delivery->reserved_on)->decrement('segments', $delivery->segments);
        if (! in_array($delivery->type, ['otp', 'test'], true)) {
            DB::table('sms_phone_daily_usage')->where('day', $delivery->reserved_on)->where('phone_hash', $delivery->phone_hash)->decrement('notifications');
        }
        $delivery->update(['reserved_on' => null]);
    }
}
