<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ServiceRequest;
use Illuminate\Validation\ValidationException;

final class ServiceRequestWorkflow
{
    private const TRANSITIONS = [
        'Pending' => ['Processing', 'Rejected'],
        'Processing' => ['Approved', 'Rejected'],
        'Approved' => ['Completed'],
        'Completed' => [],
        'Rejected' => [],
    ];

    public const COMPLETION_MESSAGE = 'Certificate requests are completed automatically when their certificate is issued. Use Prepare certificate after approval.';

    /** @return list<string> */
    public static function statuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if ($from !== $to && ! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('Status cannot move from :from to :to. Select a permitted next step.', ['from' => __($from), 'to' => __($to)])]);
        }
    }

    public function isCertificateRequest(ServiceRequest $request): bool
    {
        $catalog = app(CertificateTypeCatalog::class);

        return $catalog->allowsNew($request->request_type) || $catalog->isCertificateType($request->request_type);
    }

    /** @return array<string, string> */
    public function editableOptions(ServiceRequest $request): array
    {
        $statuses = [$request->status, ...(self::TRANSITIONS[$request->status] ?? [])];
        if ($request->status !== 'Completed' && in_array('Completed', $statuses, true) && $this->isCertificateRequest($request)) {
            $statuses = array_values(array_diff($statuses, ['Completed']));
        }

        return array_combine($statuses, array_map(fn (string $status): string => __($status), $statuses));
    }

    public function assertEditableStatus(ServiceRequest $request, string $to): void
    {
        if ($request->status !== $to && $to === 'Completed' && $this->isCertificateRequest($request)) {
            throw ValidationException::withMessages(['status' => __(self::COMPLETION_MESSAGE)]);
        }
        self::assertTransition($request->status, $to);
    }

    public function assertCompletionDocument(ServiceRequest $request): void
    {
        if ($this->isCertificateRequest($request) && ! $request->certificate()
            ->where('homeowner_id', $request->homeowner_id)->where('status', 'Issued')->exists()) {
            throw ValidationException::withMessages(['status' => __(self::COMPLETION_MESSAGE)]);
        }
    }

    public function guidanceKey(ServiceRequest $request): string
    {
        return match ($request->status) {
            'Pending' => 'Save as Processing before approval. You may also reject this request with response notes.',
            'Processing' => 'Review the request, then approve it or reject it with response notes.',
            'Approved' => $this->isCertificateRequest($request)
                ? self::COMPLETION_MESSAGE
                : 'Mark this service Completed when the work is finished.',
            'Completed' => 'This request is completed. Its status cannot be changed.',
            'Rejected' => 'This request was rejected. Its status cannot be changed.',
            default => 'Select the current status or a permitted next step.',
        };
    }
}
