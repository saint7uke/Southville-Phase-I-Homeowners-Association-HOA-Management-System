<?php

declare(strict_types=1);

namespace App\Actions\Dues;

use App\Jobs\GenerateDuesForSetting;
use App\Models\DuesSetting;
use App\Models\User;
use App\Validation\DuesSettingValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveDuesSetting
{
    public function __construct(private readonly DuesSettingValidator $validator) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): DuesSetting
    {
        Gate::forUser($actor)->authorize('create', DuesSetting::class);
        $validated = $this->validator->validate($data);

        $setting = DB::transaction(function () use ($validated, $actor): DuesSetting {
            $this->lockNameAndEnsureNoOverlap($validated);

            return DuesSetting::query()->create([...$validated, 'created_by' => $actor->id]);
        });

        DB::afterCommit(fn () => GenerateDuesForSetting::dispatch(
            $setting->id,
            now()->startOfMonth()->toDateString(),
        ));

        return $setting;
    }

    /** @param array<string, mixed> $data */
    public function update(DuesSetting $setting, array $data, User $actor): DuesSetting
    {
        Gate::forUser($actor)->authorize('update', $setting);
        $validated = $this->validator->validate($data);

        return DB::transaction(function () use ($setting, $validated): DuesSetting {
            $locked = DuesSetting::query()->lockForUpdate()->findOrFail($setting->id);
            if ($locked->obligations()->exists()) {
                foreach (['name', 'normalized_name', 'amount', 'frequency', 'due_day', 'starts_on', 'ends_on', 'is_active'] as $field) {
                    $incoming = $this->comparable($field, $validated[$field] ?? null);
                    $current = $this->comparable($field, $locked->{$field});
                    if ($incoming !== $current) {
                        throw ValidationException::withMessages([
                            $field => __('This schedule field is locked because obligations have already been generated. Close this schedule and create a replacement instead.'),
                        ]);
                    }
                }
            }

            $this->lockNameAndEnsureNoOverlap($validated, $locked->id);
            $locked->update($validated);

            return $locked->refresh();
        });
    }

    private function comparable(string $field, mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (in_array($field, ['starts_on', 'ends_on'], true) && filled($value)) {
            return CarbonImmutable::parse($value)->toDateString();
        }
        if ($field === 'amount') {
            return number_format((float) $value, 2, '.', '');
        }
        if ($field === 'is_active') {
            return (bool) $value ? '1' : '0';
        }

        return (string) ($value ?? '');
    }

    /** @param array<string, mixed> $data */
    private function lockNameAndEnsureNoOverlap(array $data, ?int $exceptId = null): void
    {
        DB::table('dues_setting_name_locks')->insertOrIgnore([
            'normalized_name' => $data['normalized_name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('dues_setting_name_locks')->where('normalized_name', $data['normalized_name'])->lockForUpdate()->first();

        $start = CarbonImmutable::parse($data['starts_on'])->startOfMonth()->toDateString();
        $end = filled($data['ends_on'] ?? null)
            ? CarbonImmutable::parse($data['ends_on'])->endOfMonth()->toDateString()
            : null;

        $conflict = DuesSetting::query()
            ->where('normalized_name', $data['normalized_name'])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where(function ($query) use ($end): void {
                if ($end !== null) {
                    $query->whereDate('starts_on', '<=', $end);
                }
            })
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'name' => __('An equivalent dues schedule already covers part of this billing period.'),
            ]);
        }
    }
}
