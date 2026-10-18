<?php

declare(strict_types=1);

namespace App\Actions\CaseFiles;

use App\Models\CaseAttachment;
use App\Models\Complaint;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Validation\CaseAttachmentRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StoreCaseAttachments
{
    /** @param array<int, UploadedFile> $files @return Collection<int, CaseAttachment> */
    public function handle(Complaint|ServiceRequest $record, array $files, User $uploader): Collection
    {
        if (count($files) > 3) {
            throw ValidationException::withMessages(['attachments' => __('A maximum of three attachments is allowed.')]);
        }

        $storedPaths = [];
        $attachments = collect();

        try {
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile
                    || ! $file->isValid()
                    || $file->getSize() > 5 * 1024 * 1024
                    || ! in_array($file->getMimeType(), CaseAttachmentRules::MIME_TYPES, true)
                    || ! in_array(strtolower($file->getClientOriginalExtension()), CaseAttachmentRules::EXTENSIONS, true)) {
                    throw ValidationException::withMessages(['attachments' => __('Every attachment must be a valid PDF, JPEG, PNG, DOC, or DOCX file no larger than 5 MB.')]);
                }

                $path = 'case-attachments/'.now()->format('Y/m').'/'.Str::ulid().'.'.$file->extension();
                $storedPaths[] = $path;
                $storedPath = Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));
                if ($storedPath !== $path || ! Storage::disk('local')->exists($path)) {
                    throw ValidationException::withMessages(['attachments' => __('The attachment could not be stored. Please try again.')]);
                }

                $attachments->push(CaseAttachment::query()->create([
                    $record instanceof Complaint ? 'complaint_id' : 'service_request_id' => $record->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'uploaded_by' => $uploader->id,
                ]));
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        return $attachments;
    }
}
