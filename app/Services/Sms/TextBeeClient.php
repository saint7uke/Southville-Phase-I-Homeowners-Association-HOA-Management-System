<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Support\PhilippineMobile;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class TextBeeClient
{
    private function request(): PendingRequest
    {
        if (blank(config('textbee.api_key')) || blank(config('textbee.device_id'))) {
            throw new SmsGatewayException('gateway_not_configured', true);
        }

        return Http::baseUrl(config('textbee.base_url'))->withHeaders(['x-api-key' => config('textbee.api_key')])
            ->acceptJson()->asJson()->connectTimeout(5)->timeout(20);
    }

    public function submit(array $messages): string
    {
        if (! config('textbee.enabled') || $messages === [] || count($messages) > config('textbee.batch_size')) {
            throw new SmsGatewayException('sending_disabled_or_invalid_batch', true);
        }
        $allowed = array_filter(array_map(PhilippineMobile::normalize(...), config('textbee.test_numbers')));
        foreach ($messages as $message) {
            if (PhilippineMobile::normalize($message['phone']) !== $message['phone']) {
                throw new SmsGatewayException('invalid_recipient', true);
            }
            if (config('textbee.test_mode') && ! in_array($message['phone'], $allowed, true)) {
                throw new SmsGatewayException('test_recipient_not_allowlisted', true);
            }
        }
        try {
            $payload = ['deviceId' => config('textbee.device_id')];
            if (count($messages) === 1) {
                $response = $this->request()->post('/send-sms', [...$payload, 'recipients' => [$messages[0]['phone']], 'message' => $messages[0]['body']]);
            } else {
                $response = $this->request()->post('/send-bulk-sms', [...$payload, 'messages' => array_map(fn ($message): array => ['recipients' => [$message['phone']], 'message' => $message['body']], $messages)]);
            }
        } catch (SmsGatewayException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new SmsGatewayException('submission_uncertain');
        }
        if ($response->status() === 429) {
            throw new SmsGatewayException('provider_rate_limited', true, true);
        }
        if (in_array($response->status(), [401, 403, 404, 422], true)) {
            throw new SmsGatewayException('provider_rejected_'.$response->status(), true);
        }
        $data = $response->json('data');
        if (! $response->successful() || ! is_array($data) || ($data['success'] ?? null) !== true
            || ! is_string($data['smsBatchId'] ?? null) || ! preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $data['smsBatchId'])
            || ($data['recipientCount'] ?? null) !== count($messages)) {
            throw new SmsGatewayException('acceptance_uncertain');
        }

        return $data['smsBatchId'];
    }

    private function read(string $path, array $query = []): array
    {
        try {
            $response = $this->request()->get($path, $query);
            $data = $response->json();
            if (! $response->successful() || ! is_array($data) || ! is_array($data['data'] ?? null)) {
                throw new SmsGatewayException('provider_read_failed', true);
            }

            return $data;
        } catch (SmsGatewayException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new SmsGatewayException('provider_read_failed', true);
        }
    }

    public function device(): array
    {
        return $this->read('/devices/'.rawurlencode((string) config('textbee.device_id')))['data'];
    }

    public function history(array $filters = []): array
    {
        return $this->read('/messages', [...$filters, 'deviceIds' => config('textbee.device_id')]);
    }

    public function batch(string $id): array
    {
        return $this->read('/devices/'.rawurlencode((string) config('textbee.device_id')).'/sms-batch/'.rawurlencode($id))['data'];
    }
}
