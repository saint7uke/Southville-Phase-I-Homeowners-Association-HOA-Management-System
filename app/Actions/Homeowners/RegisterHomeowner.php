<?php

declare(strict_types=1);

namespace App\Actions\Homeowners;

use App\Models\User;
use App\Notifications\NewHomeownerRegistration;
use App\Services\LandlordMatcher;
use App\Services\PropertyAddressAvailability;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class RegisterHomeowner
{
    public function __construct(
        private readonly PropertyAddressAvailability $addressAvailability,
        private readonly LandlordMatcher $landlordMatcher,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data): User
    {
        $documentPath = null;

        try {
            $user = $this->addressAvailability->withRegistrationLock($data, function () use ($data, &$documentPath): User {
                $landlord = $data['ownership_type'] === 'Tenant'
                    ? $this->landlordMatcher->resolveForRegistration($data)
                    : null;

                $user = User::query()->create([
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'],
                    'suffix' => $data['suffix'] ?? null,
                    'sex' => $data['sex'],
                    'contact_number' => $data['contact_number'],
                    'date_of_birth' => $data['date_of_birth'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                ]);

                $user->assignRole('homeowner');
                if (isset($data['verification_document'])) {
                    $storedPath = $data['verification_document']->store('verification_documents', 'local');
                    $documentPath = is_string($storedPath) ? $storedPath : null;
                }

                $residencyDate = $data['ownership_type'] === 'Tenant'
                    ? $data['lease_start_date']
                    : $data['residency_date'];

                $user->homeowner()->create([
                    'house_number' => $data['house_number'],
                    'street' => $data['street'],
                    'block' => $data['block'] ?? null,
                    'lot' => $data['lot'] ?? null,
                    'phase' => 'Southville Phase I',
                    'residency_date' => $residencyDate,
                    'ownership_type' => $data['ownership_type'],
                    'landlord_name' => $data['landlord_name'] ?? null,
                    'landlord_contact' => $data['landlord_contact'] ?? null,
                    'landlord_homeowner_id' => $landlord?->id,
                    'lease_start_date' => $data['lease_start_date'] ?? null,
                    'lease_end_date' => $data['lease_end_date'] ?? null,
                    'verification_document_path' => $documentPath,
                    'status' => 'Inactive',
                ]);

                return $user;
            });
        } catch (Throwable $exception) {
            if ($documentPath !== null) {
                try {
                    Storage::disk('local')->delete($documentPath);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        User::role('hoa_admin')->get()->each->notify(new NewHomeownerRegistration($user));

        return $user;
    }
}
