<?php

declare(strict_types=1);

namespace App\Actions\ServiceRequests;

use App\Actions\CaseFiles\StoreCaseAttachments;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\WorkflowStatusChanged;
use App\Validation\ServiceRequestSubmissionValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateServiceRequest
{
    public function __construct(
        private readonly StoreCaseAttachments $attachments,
        private readonly ServiceRequestSubmissionValidator $validator,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Homeowner $homeowner, array $data, UploadedFile|array|null $files = null, ?User $uploader = null): ServiceRequest
    {
        $files = $files instanceof UploadedFile ? [$files] : ($files ?? []);
        $uploader ??= $homeowner->user;
        Gate::forUser($uploader)->authorize('create', ServiceRequest::class);
        if (! $uploader->hasRole('hoa_admin') && $homeowner->user_id !== $uploader->id) {
            throw new AuthorizationException;
        }
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($homeowner, $data, $files, $uploader, &$storedPaths): ServiceRequest {
                $homeowner = Homeowner::withTrashed()->lockForUpdate()->find($homeowner->getKey());
                if (! $homeowner instanceof Homeowner) {
                    throw ValidationException::withMessages(['homeowner_id' => __('Select a current resident.')]);
                }

                $resident = User::withTrashed()->lockForUpdate()->find($homeowner->user_id);
                if (! $resident instanceof User || $resident->trashed()) {
                    throw ValidationException::withMessages(['homeowner_id' => __('Select a current resident.')]);
                }

                $validated = $this->validator->validate($homeowner, $data, $files, $uploader);
                if (filled($validated['handled_by'] ?? null)) {
                    $handler = User::query()->lockForUpdate()->find($validated['handled_by']);
                    if (! $handler instanceof User || $handler->account_status !== 'Active' || ! $handler->hasRole('hoa_staff')) {
                        throw ValidationException::withMessages(['handled_by' => __('Select an active HOA staff member.')]);
                    }
                }
                $request = $homeowner->serviceRequests()->create([...$validated, 'status' => 'Pending']);
                $storedPaths = $this->attachments->handle($request, $files, $uploader)->pluck('path')->all();

                if ($uploader->hasRole('hoa_admin') && $homeowner->user_id !== $uploader->id && SystemSetting::current()->request_notifications) {
                    $resident->notify(new WorkflowStatusChanged('service request', $request->ticket_number, 'Pending'));
                }

                return $request;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }
}
