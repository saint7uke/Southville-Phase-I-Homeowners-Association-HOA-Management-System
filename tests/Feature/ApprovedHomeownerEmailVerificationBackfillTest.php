<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ApprovedHomeownerEmailVerificationBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_verifies_approved_homeowners_except_those_with_a_post_approval_email_change(): void
    {
        $approvedAt = now()->subDay();
        $approver = User::factory()->create();
        $unchanged = $this->approvedHomeowner('legacy-unchanged@example.test', $approvedAt, $approver->id);
        $changed = $this->approvedHomeowner('legacy-changed@example.test', $approvedAt, $approver->id);

        DB::table('audit_logs')->insert([
            'user_id' => $changed->id,
            'panel' => 'homeowner',
            'action' => 'User.updated',
            'auditable_type' => User::class,
            'auditable_id' => $changed->id,
            'old_values' => json_encode(['email' => 'old-address@example.test']),
            'new_values' => json_encode(['email' => $changed->email]),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_17_000001_backfill_admin_approved_homeowner_email_verification.php');
        $migration->up();

        $this->assertSame(
            $approvedAt->toDateTimeString(),
            $unchanged->refresh()->email_verified_at?->toDateTimeString(),
        );
        $this->assertNull($changed->refresh()->email_verified_at);
    }

    private function approvedHomeowner(string $email, CarbonInterface $approvedAt, int $approverId): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'account_status' => 'Active',
            'approved_at' => $approvedAt,
            'approved_by' => $approverId,
            'email_verified_at' => null,
        ]);
        $user->homeowner()->create([
            'house_number' => (string) $user->id,
            'street' => 'Mahogany',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'status' => 'Active',
        ]);

        return $user;
    }
}
