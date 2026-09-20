<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class SystemSetting extends Model
{
    protected $fillable = [
        'hoa_name', 'contact_email', 'address', 'logo_path', 'delinquency_months',
        'complaint_notifications', 'request_notifications', 'announcement_notifications',
        'certificate_notifications', 'delinquency_notifications', 'contact_notifications',
        'staff_panel_enabled', 'homeowner_panel_enabled',
    ];

    protected function casts(): array
    {
        return [
            'delinquency_months' => 'integer',
            'complaint_notifications' => 'boolean',
            'request_notifications' => 'boolean',
            'announcement_notifications' => 'boolean',
            'certificate_notifications' => 'boolean',
            'delinquency_notifications' => 'boolean',
            'contact_notifications' => 'boolean',
            'staff_panel_enabled' => 'boolean',
            'homeowner_panel_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }

    protected static function booted(): void
    {
        self::updated(function (self $settings): void {
            if (! $settings->wasChanged('logo_path')) {
                return;
            }

            $previousPath = $settings->getOriginal('logo_path');
            if (! is_string($previousPath) || $previousPath === '' || $previousPath === $settings->logo_path) {
                return;
            }

            DB::afterCommit(fn () => Storage::disk('local')->delete($previousPath));
        });
    }
}
