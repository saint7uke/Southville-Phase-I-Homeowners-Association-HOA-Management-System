<?php

declare(strict_types=1);

namespace App\Actions\Homeowners;

use App\Models\Homeowner;
use App\Models\User;
use App\Services\Media\StorePrivateProfilePhoto;
use App\Validation\HomeownerProfileValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class UpdateOwnProfile
{
    public function __construct(
        private readonly HomeownerProfileValidator $validator,
        private readonly StorePrivateProfilePhoto $photos,
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
        unset($validated['profile_photo_upload']);

        $newPhoto = $photo instanceof UploadedFile ? $this->photos->store($homeowner, $photo) : [];
        $oldDisk = $homeowner->profile_photo_disk ?: 'local';
        $oldPath = $homeowner->profile_photo;
        $emailChanged = $actor->email !== $validated['email'];

        try {
            return DB::transaction(function () use ($actor, $homeowner, $validated, $newPhoto, $oldDisk, $oldPath, $emailChanged): Homeowner {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($actor->id);
                $lockedHomeowner = Homeowner::query()->lockForUpdate()->findOrFail($homeowner->id);

                if ($lockedHomeowner->user_id !== $lockedUser->id) {
                    throw new AuthorizationException;
                }

                $lockedUser->update(Arr::only($validated, [
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
                    ...Arr::only($validated, [
                        'house_number',
                        'street',
                        'block',
                        'lot',
                        'phase',
                        'residency_date',
                        'ownership_type',
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
            });
        } catch (Throwable $exception) {
            if ($newPhoto !== []) {
                Storage::disk($newPhoto['profile_photo_disk'])->delete($newPhoto['profile_photo']);
            }

            if ($exception instanceof QueryException && $this->isAddressUniqueConstraint($exception)) {
                throw ValidationException::withMessages([
                    'lot' => __('This Block and Lot already belongs to another homeowner.'),
                ]);
            }

            throw $exception;
        }
    }

    private function isAddressUniqueConstraint(QueryException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'homeowners_block_lot_unique')
            || str_contains($message, 'homeowners.block, homeowners.lot');
    }
}
