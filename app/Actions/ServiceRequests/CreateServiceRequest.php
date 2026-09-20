<?php

declare(strict_types=1);

namespace App\Actions\ServiceRequests;

use App\Actions\CaseFiles\StoreCaseAttachments;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateServiceRequest
{
    public function __construct(private readonly StoreCaseAttachments $attachments) {}

    /** @param array<string, mixed> $data */
    public function handle(Homeowner $homeowner, array $data, UploadedFile|array|null $files = null, ?User $uploader = null): ServiceRequest
    {
        $attachments = $files instanceof UploadedFile ? [$files] : ($files ?? []);
        $uploader ??= $homeowner->user;
        Gate::forUser($uploader)->authorize('create', ServiceRequest::class);
        if (! $uploader->hasRole('hoa_admin') && $homeowner->user_id !== $uploader->id) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($homeowner, $data, $attachments, $uploader): ServiceRequest {
            $request = $homeowner->serviceRequests()->create([...$data, 'status' => 'Pending']);
            $this->attachments->handle($request, $attachments, $uploader);

            return $request;
        });
    }
}
