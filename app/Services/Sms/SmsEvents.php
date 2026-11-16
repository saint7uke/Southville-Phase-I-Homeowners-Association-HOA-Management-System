<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Complaint;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class SmsEvents
{
    public function approval(User $user): void
    {
        app(WorkflowSms::class)->queue('approval', 'registration-approved:'.$user->id, [$user], ['user_id' => $user->id]);
    }

    public function payment(Payment $payment): void
    {
        $payment->loadMissing(['payer.user', 'homeowner.user']);
        app(WorkflowSms::class)->queue('payment', 'payment-verified:'.$payment->id,
            [$payment->payer?->user, $payment->homeowner?->user], ['payment_id' => $payment->id]);
    }

    public function complaint(Complaint $complaint): void
    {
        if (! Schema::hasColumn('complaints', 'sms_status_version')) {
            return;
        }
        $version = (string) Str::uuid();
        DB::table('complaints')->where('id', $complaint->id)->update(['sms_status_version' => $version]);
        app(WorkflowSms::class)->queue('complaint', 'complaint-status:'.$complaint->id.':'.$version,
            [$complaint->homeowner()->with('user')->first()?->user], ['complaint_id' => $complaint->id, 'version' => $version, 'status' => $complaint->status]);
    }
}
