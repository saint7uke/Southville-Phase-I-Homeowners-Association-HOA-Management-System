<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Support\PropertyIdentity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Certificate-only declarations; never updates people or infers links from names. */
final class CohabitationCertificateData
{
    public const TYPE = 'Certificate of Cohabitation';

    public const FIELDS = ['cohabitant_one', 'cohabitant_two', 'cohabitant_one_birth_date', 'cohabitant_two_birth_date',
        'cohabitant_two_homeowner_id', 'years_together', 'requester'];

    public function defaults(Homeowner $owner, ?ServiceRequest $request = null): array
    {
        $first = $request?->cohabitant_one ?? $owner->user?->full_name;

        return ['cohabitant_one' => $first, 'cohabitant_two' => $request?->cohabitant_two,
            'cohabitant_one_birth_date' => $first === $owner->user?->full_name ? $owner->user?->date_of_birth?->toDateString() : null,
            'requester' => 'one', 'cohabitant_two_homeowner_id' => null];
    }

    public function rules(array $data = []): array
    {
        $name = ['required', 'string', 'max:160', "regex:/^[\p{L}\p{M}][\p{L}\p{M}\s'’.,-]*$/u", 'not_regex:/[\p{C}]/u'];
        $years = ['required', 'integer', 'min:0'];
        foreach (['cohabitant_one_birth_date', 'cohabitant_two_birth_date'] as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value) && CarbonImmutable::hasFormat($value, 'Y-m-d')) {
                $date = CarbonImmutable::parse($value);
                if ($date->isBefore(today())) {
                    $years[] = 'max:'.(int) $date->diffInYears(today());
                }
            }
        }

        return ['cohabitant_one' => $name, 'cohabitant_two' => $name,
            'cohabitant_one_birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'cohabitant_two_birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'cohabitant_two_homeowner_id' => ['nullable', 'integer', 'min:1'],
            'years_together' => $years, 'requester' => ['required', Rule::in(['one', 'two'])]];
    }

    public function labels(): array
    {
        return ['cohabitant_one' => __('First resident full name'), 'cohabitant_two' => __('Second resident full name'),
            'cohabitant_one_birth_date' => __('First resident birth date'), 'cohabitant_two_birth_date' => __('Second resident birth date'),
            'cohabitant_two_homeowner_id' => __('Registered second resident'),
            'years_together' => __('Completed years living together'), 'requester' => __('Requesting person')];
    }

    public function validate(array $data, Homeowner $owner, ?ServiceRequest $request = null, bool $lock = false): array
    {
        foreach (['cohabitant_one', 'cohabitant_two'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = $this->normalize($data[$field]);
            }
        }
        $validated = Validator::make($data, $this->rules($data), [], $this->labels())->validate();
        $defaults = $this->defaults($owner, $request);
        if ($validated['cohabitant_one'] !== $this->normalize((string) $defaults['cohabitant_one'])) {
            $this->error('cohabitant_one', __('Use the registered or approved-request name. Correct the original record through the authorized workflow.'));
        }
        if ($request && $validated['cohabitant_two'] !== $this->normalize((string) $request->cohabitant_two)) {
            $this->error('cohabitant_two', __('Use the registered or approved-request name. Correct the original record through the authorized workflow.'));
        }
        if ($defaults['cohabitant_one_birth_date'] && $validated['cohabitant_one_birth_date'] !== $defaults['cohabitant_one_birth_date']) {
            $this->error('cohabitant_one_birth_date', __('Use the registered birth date. Correct the account before issuing this certificate.'));
        }
        $address = Arr::only($request?->address_snapshot ?? $owner->getAttributes(), ['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix']);
        if (PropertyIdentity::confirmedKey($address) === null || PropertyIdentity::confirmedKey($owner) !== PropertyIdentity::confirmedKey($address)) {
            $this->error('homeowner_id', __('Confirm the current registered phase, block and lot before preparing this certificate.'));
        }
        if (filled($validated['cohabitant_two_homeowner_id'] ?? null)) {
            $partner = Homeowner::query()->when($lock, fn ($q) => $q->lockForUpdate())->find($validated['cohabitant_two_homeowner_id']);
            $partnerUser = $partner?->user()->whereNull('deleted_at')->when($lock, fn ($q) => $q->lockForUpdate())->first();
            if (! $partner || ! $partnerUser || $partner->id === $owner->id || ! PropertyIdentity::same($owner, $partner)) {
                $this->error('cohabitant_two_homeowner_id', __('Select a different current resident registered at the same property.'));
            }
            if ($validated['cohabitant_two'] !== $this->normalize($partnerUser->full_name)) {
                $this->error('cohabitant_two', __('Use the selected resident’s registered details.'));
            }
            if ($partnerUser->date_of_birth && $validated['cohabitant_two_birth_date'] !== $partnerUser->date_of_birth->toDateString()) {
                $this->error('cohabitant_two_birth_date', __('Use the registered birth date. Correct the account before issuing this certificate.'));
            }
        }
        foreach (['cohabitant_one_birth_date', 'cohabitant_two_birth_date'] as $field) {
            if ((int) $validated['years_together'] > CarbonImmutable::parse($validated[$field])->diffInYears(today())) {
                $this->error('years_together', __('Years living together cannot exceed either person’s completed age.'));
            }
        }

        return [...$validated, 'years_together' => (int) $validated['years_together'], 'address' => $address];
    }

    public function values(array $snapshot, CarbonInterface $date): array
    {
        $data = $snapshot['cohabitation'];
        $address = $data['address'];
        $local = implode(' ', array_filter([$address['house_number'] ?? null, 'Block '.$address['block'], 'Lot '.$address['lot']]));
        $values = ['cohabitant_one' => $data['cohabitant_one'], 'cohabitant_two' => $data['cohabitant_two'],
            'address_one' => $local, 'address_two' => trim($local.' '.($address['street'] ?? '')),
            'phase_suffix' => PropertyIdentity::suffix($address['phase_suffix']),
            'years_together' => (string) $data['years_together'], 'requester_name' => $data['requester'] === 'one' ? $data['cohabitant_one'] : $data['cohabitant_two'],
            'day' => $date->format('j'), 'month' => $date->format('F')];
        // Compatibility for historical snapshots only; the corrected source has no such blank.
        if (array_key_exists('address_continuation', $data)) {
            $values['address_continuation'] = $data['address_continuation'];
        }
        foreach (['one', 'two'] as $person) {
            $birth = CarbonImmutable::parse($data['cohabitant_'.$person.'_birth_date']);
            foreach (['month' => 'm', 'day' => 'd', 'year' => 'Y'] as $part => $format) {
                $values['cohabitant_'.$person.'_birth_'.$part] = $birth->format($format);
            }
        }

        return $values;
    }

    private function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function normalize(string $value): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim(\Normalizer::normalize($value) ?: $value));
    }
}
