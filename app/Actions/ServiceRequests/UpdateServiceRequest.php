<?php

declare(strict_types=1);

namespace App\Actions\ServiceRequests;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ServiceRequestWorkflow;
use App\Support\OperationalAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateServiceRequest
{
    public function handle(ServiceRequest $request, array $data, User $actor): ServiceRequest
    {
        OperationalAccess::authorize($actor, 'manage_requests');

        return DB::transaction(function () use ($request, $data, $actor): ServiceRequest {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($request->id);
            Gate::forUser($actor)->authorize('update', $locked);
            foreach (['subject', 'details', 'admin_remarks'] as $field) {
                if (isset($data[$field]) && is_string($data[$field])) {
                    $data[$field] = trim($data[$field]);
                }
            }
            $validated = Validator::make($data, [
                'subject' => ['required', 'string', 'max:160'], 'details' => ['required', 'string', 'max:3000'],
                'status' => ['required', Rule::in(ServiceRequestWorkflow::statuses())],
                'admin_remarks' => ['required_if:status,Rejected', 'nullable', 'string', 'max:3000'], 'handled_by' => ['nullable', 'integer'],
                'request_type' => ['sometimes', Rule::in([$locked->request_type])],
                'homeowner_id' => ['sometimes', Rule::in([$locked->homeowner_id])],
                'grandfathered' => ['prohibited'], 'processed_by' => ['prohibited'], 'approved_by' => ['prohibited'],
                'processed_at' => ['prohibited'], 'approved_at' => ['prohibited'], 'completed_at' => ['prohibited'],
                'cohabitant_one' => ['prohibited'], 'cohabitant_two' => ['prohibited'], 'address_snapshot' => ['prohibited'],
            ])->validate();
            app(ServiceRequestWorkflow::class)->assertEditableStatus($locked, $validated['status']);
            if (filled($validated['handled_by'] ?? null) && ! User::query()->role('hoa_staff')->where('account_status', 'Active')->whereKey($validated['handled_by'])->exists()) {
                throw ValidationException::withMessages(['handled_by' => __('Select an active HOA staff member.')]);
            }
            if ($locked->status !== $validated['status']) {
                if ($validated['status'] === 'Processing') {
                    $locked->processed_by = $actor->id;
                }
                if ($validated['status'] === 'Approved') {
                    $locked->approved_by = $actor->id;
                }
            }
            $locked->update(Arr::only($validated, ['subject', 'details', 'status', 'admin_remarks', 'handled_by']));

            return $locked->refresh();
        });
    }
}
