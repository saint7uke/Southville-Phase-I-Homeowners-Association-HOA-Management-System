<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Homeowner;
use App\Models\User;
use App\Support\PersonName;
use App\Support\PropertyIdentity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class LandlordMatcher
{
    public const VALID_MESSAGE = 'Registered homeowner found for this address.';

    public const NOT_FOUND_MESSAGE = 'No active homeowner with this name was found at the entered address. Please check the name or contact the HOA.';

    public const AMBIGUOUS_MESSAGE = 'Multiple homeowner records match these details. Please contact the HOA.';

    /** @param array<string, mixed> $data @return array{valid: bool, message: string, homeowner: Homeowner|null} */
    public function match(array $data, bool $lock = false): array
    {
        if (app(PropertyAddressAvailability::class)->needsReview($data)) {
            return ['valid' => false, 'message' => PropertyAddressAvailability::REVIEW_MESSAGE, 'homeowner' => null];
        }
        $nameKey = PersonName::key($data['landlord_name'] ?? null);
        if ($nameKey === '') {
            return ['valid' => false, 'message' => self::NOT_FOUND_MESSAGE, 'homeowner' => null];
        }

        $query = PropertyIdentity::scope(Homeowner::query(), $data)
            ->whereIn('ownership_type', ['Owner', 'Co-owner'])
            ->where('status', 'Active')
            ->whereHas('user', fn ($query) => $query->where('account_status', 'Active')->whereNull('users.deleted_at'))
            ->with('user');

        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var Collection<int, Homeowner> $matches */
        $matches = $query->get()->filter(
            fn (Homeowner $homeowner): bool => PersonName::key($homeowner->user?->full_name) === $nameKey,
        )->values();

        if ($matches->count() > 1) {
            return ['valid' => false, 'message' => self::AMBIGUOUS_MESSAGE, 'homeowner' => null];
        }

        $homeowner = $matches->first();
        if (! $homeowner instanceof Homeowner) {
            return ['valid' => false, 'message' => self::NOT_FOUND_MESSAGE, 'homeowner' => null];
        }

        return ['valid' => true, 'message' => self::VALID_MESSAGE, 'homeowner' => $homeowner];
    }

    /** @param array<string, mixed> $data */
    public function resolveForRegistration(array $data): Homeowner
    {
        $match = $this->match($data, lock: true);
        if (! $match['valid'] || ! $match['homeowner'] instanceof Homeowner) {
            throw ValidationException::withMessages(['landlord_name' => __($match['message'])]);
        }

        $user = User::query()->lockForUpdate()->find($match['homeowner']->user_id);
        if ($user?->account_status !== 'Active') {
            throw ValidationException::withMessages(['landlord_name' => __(self::NOT_FOUND_MESSAGE)]);
        }

        return $match['homeowner'];
    }
}
