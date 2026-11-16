<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\DuesObligation;
use App\Models\Payment;
use App\Models\SmsContactPreference;
use App\Models\SmsDelivery;
use App\Models\SmsVerification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Money;
use App\Support\PhilippineMobile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SmsEligibility
{
    public function enabled(?string $type = null): bool
    {
        if (! config('textbee.enabled')) {
            return false;
        }
        $settings = SystemSetting::query()->first();

        if (! $settings) {
            return false;
        }

        return (bool) $settings->sms_enabled && ($type === null || in_array($type, ['otp', 'test'], true) || (bool) $settings->{'sms_'.$type.'_enabled'});
    }

    public function recipient(User $user, string $type): ?string
    {
        $user->loadMissing('homeowner');
        $number = PhilippineMobile::normalize($user->contact_number);
        if (! $number || $user->trashed() || $user->account_status !== 'Active' || ! $user->hasRole('homeowner')
            || ! in_array($user->homeowner?->status, ['Active', 'Delinquent'], true)) {
            return null;
        }
        $hash = PhilippineMobile::fingerprint($number);
        $preference = SmsContactPreference::where('user_id', $user->id)->first();
        if (! $preference || $preference->verified_phone_hash !== $hash || $preference->verified_at === null
            || ! $preference->{($type === 'announcement' ? 'announcement' : 'transactional').'_opt_in'}
            || DB::table('sms_phone_states')->where('phone_hash', $hash)->whereNotNull('suppressed_at')->exists()) {
            return null;
        }

        return $number;
    }

    public function current(SmsDelivery $delivery): bool
    {
        if (! $this->enabled($delivery->type) || ! $delivery->phone || ! $delivery->body) {
            return false;
        }
        $context = $delivery->context ?? [];
        if ($delivery->type === 'otp') {
            $verification = SmsVerification::find($context['verification_id'] ?? '');

            return $verification && ! $verification->invalidated_at && ! $verification->verified_at && $verification->expires_at->isFuture();
        }
        if ($delivery->type === 'test') {
            $actor = User::whereKey($context['actor_id'] ?? null)->where('account_status', 'Active')->first();

            return $actor && $actor->hasRole('hoa_admin') && $actor->can('manage_settings');
        }
        $eligible = User::with('homeowner')->whereIn('id', $delivery->user_ids)->get()->filter(fn (User $user): bool => $this->recipient($user, $delivery->type) === $delivery->phone);
        if ($eligible->isEmpty()) {
            return false;
        }

        return match ($delivery->type) {
            'approval' => User::find($context['user_id'] ?? null)?->account_status === 'Active',
            'payment' => ($payment = Payment::with(['homeowner', 'payer'])->find($context['payment_id'] ?? null)) && in_array($payment->review_status, ['Approved', 'Recorded'], true)
                && $eligible->contains(fn ($user): bool => in_array($user->id, [$payment->homeowner?->user_id, $payment->payer?->user_id], true)),
            'complaint' => ($complaint = Complaint::with('homeowner')->find($context['complaint_id'] ?? null)) && $complaint->status === ($context['status'] ?? null)
                && $complaint->sms_status_version === ($context['version'] ?? null)
                && $eligible->contains('id', $complaint->homeowner?->user_id),
            'announcement' => ($announcement = Announcement::query()->visibleToResidents()->find($context['announcement_id'] ?? null)) && $announcement->is_important
                && hash_equals($context['version'] ?? '', self::announcementVersion($announcement)),
            'due', 'overdue' => $this->openObligations($context, $eligible->pluck('id')->all(), $delivery->type),
            default => false,
        };
    }

    private function openObligations(array $context, array $userIds, string $type): bool
    {
        if (($context['date'] ?? null) !== now('Asia/Manila')->toDateString()) {
            return false;
        }

        $settings = SystemSetting::current();
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();

        return DuesObligation::whereIn('id', $context['obligation_ids'] ?? [])->whereNotIn('status', ['Paid', 'Cancelled'])
            ->whereHas('duesSetting', fn ($query) => $query->where('frequency', 'Monthly'))
            ->whereHas('homeowner', fn ($query) => $query->whereIn('user_id', $userIds))->get()
            ->contains(function ($row) use ($settings, $today, $type): bool {
                if (Money::toMinor($row->amount_due) + Money::toMinor($row->penalty_amount) <= Money::toMinor($row->amount_paid)) {
                    return false;
                }
                $due = CarbonImmutable::parse($row->due_date->toDateString(), 'Asia/Manila');
                $elapsed = (int) $due->diffInDays($today, false);

                return $type === 'due' ? $due->equalTo($today->addDays($settings->sms_due_days))
                    : ($elapsed >= 1 && ($elapsed === 1 || ($settings->sms_overdue_days > 0 && ($elapsed - 1) % $settings->sms_overdue_days === 0)));
            });
    }

    public static function announcementVersion(Announcement $announcement): string
    {
        return hash('sha256', json_encode($announcement->only(['id', 'title', 'content', 'audience', 'status', 'published_at', 'expires_at', 'is_important', 'updated_at']), JSON_THROW_ON_ERROR));
    }
}
