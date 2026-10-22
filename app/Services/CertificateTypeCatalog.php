<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CertificateType;
use App\Models\ServiceRequest;
use App\Support\CertificateTypeName;

final class CertificateTypeCatalog
{
    public const OTHER_VALUE = '__other__';

    public const ALLOWED = ['Certificate of Residency', 'Certificate of Cohabitation'];

    public function allowsNew(mixed $name): bool
    {
        return in_array(CertificateTypeName::key($name), array_map(CertificateTypeName::key(...), self::ALLOWED), true);
    }

    /** @return array<string, string> */
    public function activeOptions(bool $includeOther = false): array
    {
        $options = CertificateType::query()
            ->where('is_active', true)
            ->whereIn('normalized_key', array_map(CertificateTypeName::key(...), self::ALLOWED))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();

        return $options;
    }

    /** @return list<string> */
    public function requestTypes(): array
    {
        return array_values($this->activeOptions());
    }

    public function historicalOptions(): array
    {
        $types = array_unique([...CertificateType::query()->pluck('name')->all(), ...ServiceRequest::NON_CERTIFICATE_TYPES]);

        return array_combine($types, $types);
    }

    /** @return array<string, string> */
    public function requestOptions(): array
    {
        $types = $this->requestTypes();

        return array_combine($types, $types);
    }

    public function findByName(mixed $name, bool $activeOnly = false): ?CertificateType
    {
        return CertificateType::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->where('normalized_key', CertificateTypeName::key($name))
            ->first();
    }

    public function isCertificateType(mixed $name, bool $activeOnly = false): bool
    {
        return $this->findByName($name, $activeOnly) !== null;
    }

    public function isReservedName(mixed $name): bool
    {
        $key = CertificateTypeName::key($name);

        if ($key === CertificateTypeName::key(self::OTHER_VALUE)) {
            return true;
        }

        return collect(ServiceRequest::NON_CERTIFICATE_TYPES)
            ->contains(fn (string $reserved): bool => CertificateTypeName::key($reserved) === $key);
    }
}
