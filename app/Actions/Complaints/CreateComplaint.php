<?php

declare(strict_types=1);

namespace App\Actions\Complaints;

use App\Actions\CaseFiles\StoreCaseAttachments;
use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\User;
use App\Validation\ComplaintSubmissionValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateComplaint
{
    public function __construct(
        private readonly StoreCaseAttachments $attachments,
        private readonly ComplaintSubmissionValidator $validator,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Homeowner $homeowner, array $data, UploadedFile|array|null $attachment = null, ?User $uploader = null): Complaint
    {
        $files = $attachment instanceof UploadedFile ? [$attachment] : ($attachment ?? []);
        $uploader ??= $homeowner->user;
        Gate::forUser($uploader)->authorize('create', Complaint::class);
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($homeowner, $data, $files, $uploader, &$storedPaths): Complaint {
                $uploader = User::query()->lockForUpdate()->findOrFail($uploader->id);
                Gate::forUser($uploader)->authorize('create', Complaint::class);
                $homeowner = Homeowner::withTrashed()->lockForUpdate()->find($homeowner->getKey());
                if (! $homeowner instanceof Homeowner) {
                    throw ValidationException::withMessages(['homeowner_id' => __('Select a current resident.')]);
                }

                $validated = $this->validator->validate($homeowner, $data, $files, $uploader);
                $complaint = $homeowner->complaints()->create([...$validated, 'status' => 'Pending']);
                $storedPaths = $this->attachments->handle($complaint, $files, $uploader)->pluck('path')->all();

                return $complaint;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }
}
