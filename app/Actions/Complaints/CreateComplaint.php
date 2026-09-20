<?php

declare(strict_types=1);

namespace App\Actions\Complaints;

use App\Actions\CaseFiles\StoreCaseAttachments;
use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateComplaint
{
    public function __construct(private readonly StoreCaseAttachments $attachments) {}

    /** @param array<string, mixed> $data */
    public function handle(Homeowner $homeowner, array $data, UploadedFile|array|null $attachment = null, ?User $uploader = null): Complaint
    {
        $files = $attachment instanceof UploadedFile ? [$attachment] : ($attachment ?? []);
        $uploader ??= $homeowner->user;
        Gate::forUser($uploader)->authorize('create', Complaint::class);
        if (! $uploader->hasRole('hoa_admin') && $homeowner->user_id !== $uploader->id) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($homeowner, $data, $files, $uploader): Complaint {
            $complaint = $homeowner->complaints()->create([...$data, 'status' => 'Pending']);
            $this->attachments->handle($complaint, $files, $uploader);

            return $complaint;
        });
    }
}
