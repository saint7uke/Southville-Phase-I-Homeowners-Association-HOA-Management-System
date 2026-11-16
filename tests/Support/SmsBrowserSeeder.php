<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Announcement;
use App\Models\SmsContactPreference;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PhilippineMobile;
use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

final class SmsBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains((string) config('database.connections.sqlite.database'), 'hoa-alert-browser-sms-')) {
            throw new \RuntimeException('SMS fixtures require a disposable SMS browser database.');
        }
        $this->call(AlertBrowserSeeder::class);
        foreach (['Pending', 'Active', 'Rejected'] as $status) {
            $applicant = User::factory()->create(['account_status' => $status,
                'email' => strtolower($status).'-status-fixture@example.test',
                'approved_at' => $status === 'Active' ? now() : null,
                'rejection_reason' => $status === 'Rejected' ? str_repeat('Please provide a clearer document. ', 12) : null]);
            $applicant->assignRole('homeowner');
            $applicant->homeowner()->create(['block' => 'STATUS', 'lot' => $applicant->id, 'phase_suffix' => 'A',
                'phase' => 'Southville Phase I', 'ownership_type' => 'Owner', 'status' => $status === 'Active' ? 'Active' : 'Inactive',
                'residency_date' => '2020-01-01']);
        }
        SystemSetting::current()->update(['sms_enabled' => true]);
        $resident = User::where('email', 'resident@alerts.example.test')->sole();
        $resident->update(['contact_number' => '09171234567']);
        SmsContactPreference::create(['user_id' => $resident->id,
            'verified_phone_hash' => PhilippineMobile::fingerprint('+639171234567'), 'verified_at' => now(),
            'transactional_opt_in' => true, 'announcement_opt_in' => true, 'consented_at' => now(), 'consent_version' => 'sms-2026-11-v1']);
        $admin = User::where('email', 'admin@alerts.example.test')->sole();
        Filament::setCurrentPanel('admin');
        Auth::guard('admin')->login($admin);
        Announcement::create(['title' => 'Synthetic Important notice', 'content' => 'Disposable SMS browser record.',
            'category' => 'General', 'audience' => 'Residents', 'status' => 'Published', 'published_at' => now()->subMinute(),
            'is_important' => true, 'created_by' => $admin->id]);
        Auth::guard('admin')->logout();
    }
}
