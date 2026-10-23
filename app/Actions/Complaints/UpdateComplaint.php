<?php

declare(strict_types=1);

namespace App\Actions\Complaints;

use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\User;
use App\Support\OperationalAccess;
use App\Validation\ComplaintSubmissionValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateComplaint
{
    public function handle(Complaint $complaint, array $data, User $actor): Complaint
    {
        OperationalAccess::authorize($actor, 'manage_complaints');

        return DB::transaction(function () use ($complaint, $data, $actor): Complaint {
            $locked = Complaint::query()->lockForUpdate()->findOrFail($complaint->id);
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('view', $locked);
            if (! in_array($locked->status, ['Pending', 'Under Review'], true)) {
                throw ValidationException::withMessages(['status' => __('Finalized complaints are read-only. Reopen a resolved complaint before making changes.')]);
            }
            Gate::forUser($actor)->authorize('update', $locked);

            $administrator = $actor->hasRole('hoa_admin');
            if (! $administrator) {
                foreach (['homeowner_id', 'subject', 'category', 'description', 'handled_by'] as $field) {
                    if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $locked->getAttribute($field)) {
                        throw ValidationException::withMessages([$field => __('Assigned staff cannot change the resident\'s original complaint or assignment.')]);
                    }
                }
            }

            foreach (['subject', 'description', 'admin_remarks'] as $field) {
                if (isset($data[$field]) && is_string($data[$field])) {
                    $data[$field] = trim($data[$field]);
                }
            }
            $statuses = $locked->status === 'Pending'
                ? ['Pending', 'Under Review']
                : ($administrator ? ['Under Review', 'Resolved', 'Rejected', 'Closed', 'Dismissed'] : ['Under Review', 'Resolved']);
            $validated = Validator::make($data, [
                'priority' => ['required', Rule::in(ComplaintSubmissionValidator::PRIORITIES)],
                'status' => ['required', Rule::in($statuses)],
                'admin_remarks' => ['required_if:status,Resolved,Rejected,Closed,Dismissed', 'nullable', 'string', 'max:3000'],
                'homeowner_id' => [$administrator ? 'required' : 'sometimes', 'integer'],
                'subject' => [$administrator ? 'required' : 'sometimes', 'string', 'max:160'],
                'category' => [$administrator ? 'required' : 'sometimes', Rule::in(ComplaintSubmissionValidator::CATEGORIES)],
                'description' => [$administrator ? 'required' : 'sometimes', 'string', 'min:20', 'max:5000'],
                'handled_by' => ['nullable', 'integer'],
                'ticket_number' => ['prohibited'], 'resolved_at' => ['prohibited'],
                'attachment' => ['prohibited'], 'attachment_name' => ['prohibited'], 'attachments' => ['prohibited'],
            ], ComplaintSubmissionValidator::messages() + [
                'status.in' => __('Select a permitted next complaint status. Move Pending complaints to Under Review before resolving them.'),
                'admin_remarks.required_if' => __('Resolution notes are required for a terminal complaint status.'),
            ])->validate();

            if ($administrator) {
                if ((int) $validated['homeowner_id'] !== $locked->homeowner_id
                    && ! Homeowner::query()->whereKey($validated['homeowner_id'])->whereHas('user', fn ($query) => $query->whereNull('users.deleted_at'))->exists()) {
                    throw ValidationException::withMessages(['homeowner_id' => __('Select a current resident.')]);
                }
                if (filled($validated['handled_by'] ?? null) && (int) $validated['handled_by'] !== $locked->handled_by
                    && ! User::query()->role('hoa_staff')->where('account_status', 'Active')->whereKey($validated['handled_by'])->exists()) {
                    throw ValidationException::withMessages(['handled_by' => __('Select an active HOA staff member.')]);
                }
            }
            $fields = $administrator
                ? ['homeowner_id', 'subject', 'category', 'description', 'handled_by', 'priority', 'status', 'admin_remarks']
                : ['priority', 'status', 'admin_remarks'];
            $locked->update(Arr::only($validated, $fields));

            return $locked->refresh();
        });
    }
}
