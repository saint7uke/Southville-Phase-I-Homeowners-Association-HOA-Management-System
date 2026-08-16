<?php

declare(strict_types=1);

namespace App\Actions\ServiceRequests;

use App\Models\Homeowner;
use App\Models\ServiceRequest;
use Illuminate\Support\Str;

final class CreateServiceRequest
{
    /** @param array<string, mixed> $data */
    public function handle(Homeowner $homeowner, array $data): ServiceRequest
    {
        return $homeowner->serviceRequests()->create([
            ...$data,
            'ticket_number' => 'REQ-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
            'status' => 'Pending',
        ]);
    }
}
