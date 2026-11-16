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
        'complaint_notifications', 'request_notifications', 'payment_notifications', 'announcement_notifications',
        'certificate_notifications', 'delinquency_notifications', 'contact_notifications',
        'staff_panel_enabled', 'homeowner_panel_enabled',
        'sms_enabled', 'sms_approval_enabled', 'sms_due_enabled', 'sms_overdue_enabled', 'sms_payment_enabled', 'sms_complaint_enabled',
        'sms_announcement_enabled', 'sms_incoming_enabled', 'sms_due_days', 'sms_overdue_days', 'sms_daily_segments',
    ];

    protected function casts(): array
    {
        return [
            'delinquency_months' => 'integer',
            'complaint_notifications' => 'boolean',
            'request_notifications' => 'boolean',
            'payment_notifications' => 'boolean',
            'announcement_notifications' => 'boolean',
            'certificate_notifications' => 'boolean',
            'delinquency_notifications' => 'boolean',
            'contact_notifications' => 'boolean',
            'staff_panel_enabled' => 'boolean',
            'homeowner_panel_enabled' => 'boolean',
            'sms_enabled' => 'boolean', 'sms_approval_enabled' => 'boolean', 'sms_due_enabled' => 'boolean',
            'sms_overdue_enabled' => 'boolean', 'sms_payment_enabled' => 'boolean', 'sms_complaint_enabled' => 'boolean',
            'sms_announcement_enabled' => 'boolean', 'sms_incoming_enabled' => 'boolean',
            'sms_due_days' => 'integer', 'sms_overdue_days' => 'integer', 'sms_daily_segments' => 'integer',
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
