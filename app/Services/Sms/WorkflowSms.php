<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Jobs\DeliverSmsBatch;
use App\Models\SmsBatch;
use App\Models\SmsDelivery;
use App\Models\User;
use App\Support\PhilippineMobile;
use App\Support\SmsText;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WorkflowSms
{
    public function __construct(private readonly SmsEligibility $eligibility) {}

    /** Durable intent; this method never contacts a provider. */
    public function queue(string $type, string $event, iterable $users, array $context = [], ?CarbonInterface $expires = null): array
    {
        if (! array_key_exists($type, SmsText::TEMPLATES) || $type === 'test') {
            throw new \InvalidArgumentException('Unsupported SMS workflow.');
        }
        if (! $this->eligibility->enabled($type)) {
            return [];
        }

        return DB::transaction(function () use ($type, $event, $users, $context, $expires): array {
            $groups = [];
            foreach (collect($users)->filter()->unique('id') as $user) {
                $phone = PhilippineMobile::normalize($user->contact_number);
                $hash = PhilippineMobile::fingerprint($phone ?? 'invalid-user:'.$user->id);
                $groups[$hash]['phone'] = $phone;
                $groups[$hash]['users'][] = $user;
            }
            $new = [];
            foreach ($groups as $hash => $group) {
                $eligible = collect($group['users'])->filter(fn (User $user): bool => $this->eligibility->recipient($user, $type) !== null);
                $record = SmsDelivery::firstOrCreate(['event_key' => hash('sha256', $event), 'phone_hash' => $hash], [
                    'type' => $type, 'user_ids' => collect($group['users'])->pluck('id')->all(), 'context' => $context,
                    'phone' => $eligible->isNotEmpty() ? $group['phone'] : null,
                    'body' => $eligible->isNotEmpty() ? SmsText::TEMPLATES[$type] : null,
                    'segments' => SmsText::segments(SmsText::TEMPLATES[$type]),
                    'status' => $eligible->isNotEmpty() ? 'Queued' : 'Skipped',
                    'failure_code' => $eligible->isNotEmpty() ? null : 'recipient_unverified_or_ineligible',
                ]);
                if ($record->wasRecentlyCreated && $record->status === 'Queued') {
                    $new[] = $record;
                }
            }

            return $this->batches($type, $new, $expires ?? now()->addDay());
        }, 3);
    }

    public function raw(string $type, string $event, string $phone, string $body, array $context, CarbonInterface $expires, bool $dispatch = true): SmsBatch
    {
        if (! in_array($type, ['otp', 'test'], true) || ! $this->eligibility->enabled($type) || PhilippineMobile::normalize($phone) !== $phone) {
            throw new SmsGatewayException('sending_disabled_or_invalid_recipient', true);
        }

        return DB::transaction(function () use ($type, $event, $phone, $body, $context, $expires, $dispatch): SmsBatch {
            $delivery = SmsDelivery::firstOrCreate(['event_key' => hash('sha256', $event), 'phone_hash' => PhilippineMobile::fingerprint($phone)], [
                'type' => $type, 'user_ids' => [], 'context' => $context, 'phone' => $phone, 'body' => $body, 'segments' => SmsText::segments($body),
            ]);
            if ($delivery->batch_id !== null) {
                return SmsBatch::findOrFail($delivery->batch_id);
            }
            $ids = $this->batches($type, [$delivery], $expires, $dispatch);

            return SmsBatch::findOrFail($ids[0]);
        });
    }

    private function batches(string $type, array $deliveries, CarbonInterface $expires, bool $dispatch = true): array
    {
        $ids = [];
        foreach (array_chunk($deliveries, (int) config('textbee.batch_size')) as $chunk) {
            $batch = SmsBatch::create(['type' => $type, 'expires_at' => $expires]);
            foreach ($chunk as $delivery) {
                $delivery->update(['batch_id' => $batch->id]);
            }
            $ids[] = $batch->id;
            if ($dispatch) {
                DB::afterCommit(fn () => $this->dispatch($batch->id));
            }
        }

        return $ids;
    }

    public function dispatch(int $batchId): void
    {
        try {
            $batch = SmsBatch::find($batchId);
            if ($batch && $batch->status === 'Queued') {
                DeliverSmsBatch::dispatch($batchId)->onConnection(config('textbee.connection'))->onQueue($batch->type === 'otp' ? 'sms-otp' : 'sms');
            }
        } catch (\Throwable) {
            Log::warning('SMS dispatch unavailable.', ['sms_batch_id' => $batchId, 'failure_code' => 'queue_unavailable']);
        }
    }
}
