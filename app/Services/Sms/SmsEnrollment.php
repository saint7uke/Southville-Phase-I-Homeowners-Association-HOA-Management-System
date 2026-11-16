<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\SmsContactPreference;
use App\Models\SmsDelivery;
use App\Models\SmsVerification;
use App\Models\User;
use App\Support\PhilippineMobile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SmsEnrollment
{
    public function binding(?User $user = null): string
    {
        if ($user) {
            return PhilippineMobile::fingerprint('user:'.$user->id);
        }
        if (! session()->has('sms_binding')) {
            session()->put('sms_binding', Str::random(64));
        }

        return PhilippineMobile::fingerprint('registration:'.session('sms_binding'));
    }

    public function request(mixed $input, string $binding, string $ip): SmsVerification
    {
        $phone = PhilippineMobile::normalize($input);
        if (! $phone || ! app(SmsEligibility::class)->enabled('otp')) {
            throw ValidationException::withMessages(['contact_number' => __('SMS verification is unavailable or the mobile number is invalid. You may continue without SMS enrollment.')]);
        }
        $hash = PhilippineMobile::fingerprint($phone);

        return DB::transaction(function () use ($phone, $hash, $binding, $ip): SmsVerification {
            DB::table('sms_phone_states')->insertOrIgnore(['phone_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('sms_phone_states')->where('phone_hash', $hash)->lockForUpdate()->first();
            foreach ([['sms-otp-minute:'.$hash, 1, 60], ['sms-otp-hour:'.$hash, 5, 3600], ['sms-otp-ip:'.PhilippineMobile::fingerprint($ip), 10, 3600]] as [$key, $limit, $seconds]) {
                if (RateLimiter::hit($key, $seconds) > $limit) {
                    throw ValidationException::withMessages(['contact_number' => __('Too many SMS verification requests. Please try again later.')]);
                }
            }
            SmsVerification::where('binding_hash', $binding)->where('phone_hash', $hash)->whereNull('consumed_at')->update(['invalidated_at' => now(), 'code_hash' => null]);
            $code = (string) random_int(100000, 999999);
            $record = SmsVerification::create(['id' => (string) Str::uuid(), 'binding_hash' => $binding, 'phone_hash' => $hash,
                'phone' => $phone, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5)]);
            app(WorkflowSms::class)->raw('otp', 'otp:'.$record->id, $phone,
                'Southville Phase I HOA: Your verification code is '.$code.'. It expires in 5 minutes. Do not share this code.',
                ['verification_id' => $record->id], $record->expires_at);

            return $record;
        }, 3);
    }

    public function confirm(string $id, string $code, string $binding): void
    {
        Validator::make(['verification_id' => $id, 'code' => $code], ['verification_id' => ['required', 'uuid'], 'code' => ['required', 'regex:/\A[0-9]{6}\z/']])->validate();
        $valid = DB::transaction(function () use ($id, $code, $binding): bool {
            $record = SmsVerification::lockForUpdate()->find($id);
            if (! $record || ! hash_equals($record->binding_hash, $binding) || $record->invalidated_at || $record->consumed_at || $record->verified_at
                || $record->expires_at->isPast() || $record->attempts >= 5 || ! $record->code_hash) {
                return false;
            }
            $record->attempts++;
            if (! Hash::check($code, $record->code_hash)) {
                $record->save();

                return false;
            }
            $record->update(['verified_at' => now(), 'proof_expires_at' => now()->addMinutes(30), 'code_hash' => null]);
            SmsDelivery::where('type', 'otp')->where('event_key', hash('sha256', 'otp:'.$record->id))->update(['body' => null]);

            return true;
        });
        if (! $valid) {
            throw ValidationException::withMessages(['code' => __('The SMS verification code is invalid, expired, or already used.')]);
        }
    }

    public function apply(User $user, array $input, string $binding): void
    {
        $data = Validator::make($input, [
            'sms_transactional_opt_in' => ['sometimes', 'boolean'], 'sms_announcement_opt_in' => ['sometimes', 'boolean'],
            'sms_verification_id' => ['nullable', 'uuid'], 'sms_verified_at' => ['prohibited'], 'verified_phone_hash' => ['prohibited'],
        ])->validate();
        $transactional = (bool) ($data['sms_transactional_opt_in'] ?? false);
        $announcements = (bool) ($data['sms_announcement_opt_in'] ?? false);
        if (! $transactional && ! $announcements) {
            SmsContactPreference::where('user_id', $user->id)->update(['transactional_opt_in' => false, 'announcement_opt_in' => false, 'consented_at' => now()]);

            return;
        }
        if (! app(SmsEligibility::class)->enabled()) {
            throw ValidationException::withMessages(['sms_verification_id' => __('SMS enrollment is currently unavailable. You may continue without SMS enrollment.')]);
        }
        DB::transaction(function () use ($user, $data, $binding, $transactional, $announcements): void {
            $phone = PhilippineMobile::normalize($user->contact_number);
            if (! $phone) {
                throw ValidationException::withMessages(['sms_verification_id' => __('Verify your current mobile number before enabling SMS.')]);
            }
            $hash = PhilippineMobile::fingerprint($phone);
            DB::table('sms_phone_states')->insertOrIgnore(['phone_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $state = DB::table('sms_phone_states')->where('phone_hash', $hash)->lockForUpdate()->first();
            $preference = SmsContactPreference::firstOrNew(['user_id' => $user->id]);
            if (filled($data['sms_verification_id'] ?? null)) {
                $proof = SmsVerification::lockForUpdate()->find($data['sms_verification_id']);
                if (! $proof || ! hash_equals($proof->binding_hash, $binding) || $proof->phone_hash !== $hash || ! $proof->verified_at
                    || ! $proof->proof_expires_at?->isFuture() || $proof->invalidated_at || $proof->consumed_at
                    || ($state->suppressed_at && $proof->verified_at->lte($state->suppressed_at))) {
                    throw ValidationException::withMessages(['sms_verification_id' => __('Verify your current mobile number before enabling SMS.')]);
                }
                $proof->update(['consumed_at' => now(), 'phone' => null]);
                $preference->fill(['verified_phone_hash' => $hash, 'verified_at' => $proof->verified_at]);
                DB::table('sms_phone_states')->where('phone_hash', $hash)->update(['suppressed_at' => null, 'updated_at' => now()]);
            } elseif ($preference->verified_phone_hash !== $hash || ! $preference->verified_at || $state->suppressed_at) {
                throw ValidationException::withMessages(['sms_verification_id' => __('Verify your current mobile number before enabling SMS.')]);
            }
            $preference->fill(['transactional_opt_in' => $transactional, 'announcement_opt_in' => $announcements,
                'consented_at' => now(), 'consent_version' => 'sms-2026-11-v1'])->save();
        });
    }

    public function invalidateChangedPhone(User $user): void
    {
        if (PhilippineMobile::normalize($user->getRawOriginal('contact_number')) !== PhilippineMobile::normalize($user->contact_number)) {
            SmsContactPreference::where('user_id', $user->id)->update(['verified_at' => null, 'verified_phone_hash' => null,
                'transactional_opt_in' => false, 'announcement_opt_in' => false]);
        }
    }
}
