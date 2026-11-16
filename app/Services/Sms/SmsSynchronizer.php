<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\SmsBatch;
use App\Models\SmsDelivery;
use App\Models\SystemSetting;
use App\Support\PhilippineMobile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SmsSynchronizer
{
    public function __construct(private readonly TextBeeClient $client) {}

    public function outgoing(): void
    {
        SmsBatch::whereNotNull('provider_batch_id')->where('accepted_at', '>=', now()->subDays(90))
            ->where(fn ($query) => $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subMinute()))
            ->orderBy('id')->chunkById(100, function ($batches): void {
                foreach ($batches as $batch) {
                    $response = $this->client->batch($batch->provider_batch_id);
                    if (! is_array($response['messages'] ?? null)) {
                        throw new SmsGatewayException('malformed_batch_history', true);
                    }
                    foreach ($response['messages'] as $message) {
                        $this->outgoingMessage($batch, $message);
                    }
                    $batch->update(['last_checked_at' => now()]);
                }
            });
    }

    public function outgoingMessage(SmsBatch $batch, array $message): void
    {
        $phone = PhilippineMobile::normalize($message['recipient'] ?? null);
        $device = is_array($message['device'] ?? null) ? ($message['device']['_id'] ?? null) : ($message['device'] ?? null);
        if (! $phone || $device !== config('textbee.device_id') || ($message['smsBatch'] ?? null) !== $batch->provider_batch_id
            || ! is_string($message['_id'] ?? null) || ! preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $message['_id'])) {
            return;
        }
        $map = ['pending' => 'Pending', 'dispatched' => 'Dispatched', 'sent' => 'Sent', 'delivered' => 'Delivered', 'failed' => 'Failed', 'unknown' => 'Unknown', 'delivery_failed' => 'Unknown'];
        $status = $map[$message['status'] ?? 'unknown'] ?? 'Unknown';
        if (! is_string($message['updatedAt'] ?? null) || trim($message['updatedAt']) === '') {
            return;
        }
        try {
            $updated = CarbonImmutable::parse($message['updatedAt']);
        } catch (\Throwable) {
            return;
        }
        DB::transaction(function () use ($batch, $message, $phone, $status, $updated): void {
            $row = SmsDelivery::where('batch_id', $batch->id)->where('phone_hash', PhilippineMobile::fingerprint($phone))->lockForUpdate()->first();
            if (! $row || ($row->provider_message_id && $row->provider_message_id !== $message['_id'])
                || ($row->provider_updated_at && $row->provider_updated_at->gte($updated))
                || ($row->status === 'Delivered' && $status !== 'Delivered')
                || ($row->status === 'Sent' && in_array($status, ['Pending', 'Dispatched'], true))) {
                return;
            }
            $attributes = ['provider_message_id' => $message['_id'], 'provider_status' => $message['status'] ?? 'unknown',
                'provider_updated_at' => $updated, 'status' => $status, 'failure_code' => in_array($status, ['Failed', 'Unknown'], true) ? 'provider_'.strtolower($status) : null];
            foreach (['sentAt' => 'sent_at', 'deliveredAt' => 'delivered_at'] as $source => $target) {
                if (is_string($message[$source] ?? null)) {
                    try {
                        $attributes[$target] = CarbonImmutable::parse($message[$source]);
                    } catch (\Throwable) {
                        // A malformed optional timestamp is not a delivery assertion.
                    }
                }
            }
            $row->update($attributes);
        });
    }

    public function incoming(bool $overlap = false): void
    {
        if (! SystemSetting::current()->sms_incoming_enabled) {
            return;
        }
        $key = 'incoming:'.config('textbee.device_id');
        $cursor = $overlap ? null : DB::table('sms_sync_states')->where('key', $key)->value('value');
        $filters = ['direction' => 'received', 'order' => 'asc', 'limit' => 100];
        if ($cursor) {
            $filters['cursor'] = $cursor;
        } else {
            $filters['from'] = ($overlap ? now()->subDays(2) : now()->subMinute())->toIso8601String();
        }
        // Bound each tick; a saved cursor resumes larger backlogs on the next tick.
        for ($page = 0; $page < 20; $page++) {
            $response = $this->client->history($filters);
            if (! is_array($response['data']) || ! is_array($response['meta'] ?? null)) {
                throw new SmsGatewayException('malformed_incoming_history', true);
            }
            foreach ($response['data'] as $message) {
                $this->incomingMessage($message);
            }
            $next = $response['meta']['nextCursor'] ?? null;
            if (is_string($next) && $next !== '') {
                if (! $overlap) {
                    DB::table('sms_sync_states')->updateOrInsert(['key' => $key], ['value' => $next, 'updated_at' => now(), 'created_at' => now()]);
                }
                unset($filters['from']);
                $filters['cursor'] = $next;
            }
            if (! ($response['meta']['hasMore'] ?? false) || ! $next) {
                DB::table('sms_sync_states')->updateOrInsert(['key' => 'incoming-healthy'], ['value' => now()->toIso8601String(), 'updated_at' => now(), 'created_at' => now()]);
                break;
            }
        }
    }

    public function incomingMessage(array $message): void
    {
        if (! SystemSetting::current()->sms_incoming_enabled) {
            return;
        }
        $device = is_array($message['device'] ?? null) ? ($message['device']['_id'] ?? null) : ($message['device'] ?? null);
        $phone = PhilippineMobile::normalize($message['sender'] ?? null);
        if (($message['status'] ?? null) !== 'received' || $device !== config('textbee.device_id') || ! $phone
            || ! is_string($message['_id'] ?? null) || ! preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $message['_id']) || ! is_string($message['message'] ?? null)) {
            return;
        }
        $hash = PhilippineMobile::fingerprint($phone);
        DB::transaction(function () use ($message, $hash, $phone): void {
            $record = SmsDelivery::firstOrCreate(['provider_message_id' => $message['_id']], [
                'event_key' => hash('sha256', 'received:'.$message['_id']), 'phone_hash' => $hash, 'direction' => 'incoming',
                'type' => 'incoming', 'user_ids' => [], 'phone' => $phone, 'body' => mb_substr($message['message'], 0, 10000),
                'status' => 'Received', 'provider_status' => 'received', 'segments' => 0,
            ]);
            if ($record->wasRecentlyCreated && in_array(strtoupper(trim($message['message'])), ['STOP', 'UNSUBSCRIBE', 'CANCEL'], true)) {
                DB::table('sms_phone_states')->insertOrIgnore(['phone_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('sms_phone_states')->where('phone_hash', $hash)->lockForUpdate()->first();
                DB::table('sms_phone_states')->where('phone_hash', $hash)->update(['suppressed_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}
