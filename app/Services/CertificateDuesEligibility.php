<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CertificateDuesBlocked;
use App\Models\DuesObligation;
use App\Models\Homeowner;
use App\Support\Money;
use App\Support\PropertyIdentity;
use Illuminate\Support\Facades\DB;

final class CertificateDuesEligibility
{
    public const OUTSTANDING = 'You have outstanding monthly dues. Please visit the HOA office to settle your balance before requesting a certificate.';

    public const REVIEW = 'Your property dues account needs HOA review. Please visit the HOA office before requesting a certificate.';

    /** Serialize assessment creation, property changes and certificate decisions, including an empty ledger. */
    public function lockForResident(int $id): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Certificate eligibility locking requires a transaction.');
        }
        $resident = Homeowner::withTrashed()->findOrFail($id);
        app(PropertyAddressAvailability::class)->lockProperty($resident->getAttributes());
        $current = Homeowner::withTrashed()->lockForUpdate()->findOrFail($id);
        if (! PropertyIdentity::same($resident, $current)) {
            throw new CertificateDuesBlocked('homeowner_id', __(self::REVIEW));
        }
    }

    /** @return array{state:string,balance:string,message:?string} */
    public function inspect(Homeowner $resident, bool $lock = false): array
    {
        if ($lock && DB::transactionLevel() === 0) {
            throw new \LogicException('Certificate eligibility locking requires a transaction.');
        }
        $resident = Homeowner::withTrashed()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($resident->id);
        $accounts = app(PropertyDuesAccounts::class);
        $sameProperty = PropertyIdentity::scope(Homeowner::withTrashed(), $resident)->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get();
        $liable = $accounts->primaryForAddress(PropertyIdentity::key($resident), $lock);
        $ids = [$resident->id];
        $review = $resident->trashed() || blank($resident->block) || blank($resident->lot);
        if ($liable !== null && $accounts->eligiblePayers($liable, $lock)->whereKey($resident->id)->first() !== null) {
            $ids[] = $liable->id;
        } elseif ($resident->ownership_type === 'Tenant') {
            $review = true;
        }

        // Do not guess between confirmed phases and same-block/lot legacy identities.
        $peers = Homeowner::withTrashed()->get(['id', 'phase', 'phase_suffix', 'block', 'lot', 'house_number', 'street', 'address_key', 'property_key'])
            ->filter(fn (Homeowner $peer): bool => PropertyIdentity::baseKey($peer) === PropertyIdentity::baseKey($resident));
        $peers = $peers->keyBy('id')->merge($sameProperty->keyBy('id'))->values();
        foreach ($peers as $peer) {
            if (! PropertyIdentity::same($peer, $resident)
                && (PropertyIdentity::confirmedKey($peer) === null || PropertyIdentity::confirmedKey($resident) === null)) {
                $review = true;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($lock) {
            Homeowner::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        }
        // Include personal historical assessments. Shared-property assessments on an
        // unresolvable former/competing liable account require review, not reassignment.
        $obligations = DuesObligation::query()->whereIn('homeowner_id', array_unique([...$ids, ...$peers->modelKeys()]))
            ->where(fn ($query) => $query->where('billing_year', '<', now('Asia/Manila')->year)
                ->orWhere(fn ($query) => $query->where('billing_year', now('Asia/Manila')->year)->where('billing_month', '<=', now('Asia/Manila')->month)))
            ->where('status', '!=', 'Cancelled')->whereHas('duesSetting', fn ($query) => $query->where('frequency', 'Monthly'))
            ->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get();
        $balance = 0;
        foreach ($obligations as $obligation) {
            $remaining = max(0, Money::toMinor((string) $obligation->amount_due) + Money::toMinor((string) $obligation->penalty_amount) - Money::toMinor((string) $obligation->amount_paid));
            if (in_array($obligation->homeowner_id, $ids, true)) {
                $balance += $remaining;
            } elseif ($remaining > 0 && (PropertyIdentity::same($peers->firstWhere('id', $obligation->homeowner_id), $resident)
                || PropertyIdentity::confirmedKey($resident) === null)) {
                $review = true;
            }
        }

        return ['state' => $review ? 'review' : ($balance > 0 ? 'outstanding' : 'eligible'),
            'balance' => Money::fromMinor($balance), 'message' => $review ? self::REVIEW : ($balance > 0 ? self::OUTSTANDING : null)];
    }

    public function assertEligible(Homeowner $resident, string $field = 'request_type', bool $lock = false): void
    {
        $result = $this->inspect($resident, $lock);
        if ($result['state'] !== 'eligible') {
            throw new CertificateDuesBlocked($field, __($result['message']));
        }
    }
}
