<?php

declare(strict_types=1);

namespace App\Actions\Homeowners;

use App\Models\Homeowner;
use App\Models\User;
use App\Services\Media\StorePrivateProfilePhoto;
use App\Services\PropertyAddressAvailability;
use App\Validation\HomeownerProfileValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class UpdateOwnProfile
{
    public function __construct(
        private readonly HomeownerProfileValidator $validator,
        private readonly StorePrivateProfilePhoto $photos,
        private readonly PropertyAddressAvailability $addressAvailability,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, array $input): Homeowner
    {
        $homeowner = $actor->homeowner;
        if (! $actor->hasRole('homeowner') || $homeowner === null || ! $actor->can('update_own_profile')) {
            throw new AuthorizationException;
        }

        Gate::forUser($actor)->authorize('update', $homeowner);
        $validated = $this->validator->validate($actor, $homeowner, $input);
        $photo = $validated['profile_photo_upload'] ?? null;

        try {
            $newPhoto = $photo instanceof UploadedFile ? $this->photos->store($homeowner, $photo) : [];
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'profile_photo_upload' => __('The profile photo could not be processed. Upload a valid JPEG, PNG, or WebP image.'),
            ]);
        }

        $oldDisk = $homeowner->profile_photo_disk ?: 'local';
        $oldPath = $homeowner->profile_photo;
        $emailChanged = $actor->email !== $validated['email'];

        try {
            return $this->addressAvailability->withAddressLock($validated, $homeowner->id, function () use ($actor, $homeowner, $validated, $newPhoto, $oldDisk, $oldPath, $emailChanged): Homeowner {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($actor->id);
                $lockedHomeowner = Homeowner::query()->lockForUpdate()->findOrFail($homeowner->id);

                if ($lockedHomeowner->user_id !== $lockedUser->id) {
                    throw new AuthorizationException;
                }

                $finalValidated = $this->validator->validate($lockedUser, $lockedHomeowner, $validated);

                $lockedUser->update(Arr::only($finalValidated, [
                    'first_name',
                    'middle_name',
                    'last_name',
                    'suffix',
                    'sex',
                    'date_of_birth',
                    'contact_number',
                    'email',
                ]));

                $lockedHomeowner->update([
                    ...Arr::only($finalValidated, [
                        'residency_date',
                        'emergency_contact_name',
                        'emergency_contact_number',
                    ]),
                    ...$newPhoto,
                ]);

                DB::afterCommit(function () use ($lockedUser, $newPhoto, $oldDisk, $oldPath, $emailChanged): void {
                    if ($newPhoto !== [] && filled($oldPath) && $oldPath !== $newPhoto['profile_photo']) {
                        try {
                            Storage::disk($oldDisk)->delete((string) $oldPath);
                        } catch (Throwable $exception) {
                            report($exception);
                        }
                    }

                    if ($emailChanged) {
                        try {
                            $lockedUser->sendEmailVerificationNotification();
                        } catch (Throwable $exception) {
                            report($exception);
                        }
                    }
                });

                return $lockedHomeowner->refresh()->load('user');
            }, assertAvailable: false);
        } catch (QueryException $exception) {
            $this->deleteNewPhoto($newPhoto);

            $availability = $this->validator->emailAvailability($actor, $homeowner, $validated['email'] ?? null);
            if (! $availability['valid']) {
                throw ValidationException::withMessages(['email' => __($availability['message'])]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteNewPhoto($newPhoto);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $newPhoto */
    private function deleteNewPhoto(array $newPhoto): void
    {
        if ($newPhoto !== []) {
            Storage::disk($newPhoto['profile_photo_disk'])->delete($newPhoto['profile_photo']);
        }
    }
}
