<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Jobs\DeliverSmsBatch;
use App\Models\AuditLog;
use App\Models\SmsBatch;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PhilippineMobile;
use App\Support\SmsText;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SmsManagement
{
    public function authorize(?User $actor): void
    {
        abort_unless($actor && ! $actor->trashed() && $actor->account_status === 'Active' && $actor->hasRole('hoa_admin') && $actor->can('manage_settings'), 403);
    }

    public function settings(User $actor, array $input): void
    {
        $this->authorize($actor);
        $rules = [];
        foreach (['enabled', 'approval_enabled', 'due_enabled', 'overdue_enabled', 'payment_enabled', 'complaint_enabled', 'announcement_enabled', 'incoming_enabled'] as $key) {
            $rules['sms_'.$key] = ['required', 'boolean'];
        }
        $rules += ['sms_due_days' => ['required', 'integer', 'between:0,31'], 'sms_overdue_days' => ['required', 'integer', 'between:0,365'], 'sms_daily_segments' => ['required', 'integer', 'between:1,10000']];
        SystemSetting::current()->update(Validator::make($input, $rules)->validate());
    }

    public function test(User $actor, mixed $input): SmsBatch
    {
        $this->authorize($actor);
        Validator::make(['number' => $input], ['number' => PhilippineMobile::inputRules()], [
            'number.regex' => __('Enter an 11-digit Philippine mobile number beginning with 09.'),
        ])->validate();
        $phone = PhilippineMobile::normalize($input);
        if (! $phone || ! app(SmsEligibility::class)->enabled('test')) {
            throw ValidationException::withMessages(['number' => __('SMS sending is disabled or the test number is invalid.')]);
        }
        foreach ([['sms-test-minute:'.$actor->id, 1, 60], ['sms-test-day:'.$actor->id.':'.now('Asia/Manila')->toDateString(), 5, 86400]] as [$key, $limit, $seconds]) {
            if (RateLimiter::hit($key, $seconds) > $limit) {
                throw ValidationException::withMessages(['number' => __('The test SMS rate limit has been reached.')]);
            }
        }
        $batch = app(WorkflowSms::class)->raw('test', 'test:'.Str::uuid(), $phone, SmsText::TEMPLATES['test'], ['actor_id' => $actor->id], now()->addMinutes(5), false);
        AuditLog::create(['user_id' => $actor->id, 'panel' => 'admin', 'action' => 'Sms.test_requested', 'auditable_type' => SmsBatch::class,
            'auditable_id' => $batch->id, 'new_values' => ['sms_batch_id' => $batch->id]]);
        app()->call([new DeliverSmsBatch($batch->id), 'handle']);

        return $batch->fresh();
    }
}
