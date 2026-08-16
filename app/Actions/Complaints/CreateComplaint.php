<?php

declare(strict_types=1);

namespace App\Actions\Complaints;

use App\Models\Complaint;
use App\Models\Homeowner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class CreateComplaint
{
    /** @param array<string, mixed> $data */
    public function handle(Homeowner $homeowner, array $data, ?UploadedFile $attachment = null): Complaint
    {
        $path = $attachment?->storeAs(
            'complaint-attachments/'.now()->format('Y/m'),
            Str::ulid().'.'.$attachment->extension(),
            'local',
        );

        return $homeowner->complaints()->create([
            ...$data,
            'ticket_number' => 'CMP-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
            'attachment' => $path,
            'attachment_name' => $attachment?->getClientOriginalName(),
            'status' => 'Pending',
        ]);
    }
}
