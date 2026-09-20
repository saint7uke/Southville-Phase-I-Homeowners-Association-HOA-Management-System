<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeownerPanelResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_homeowner_can_open_every_dedicated_self_service_resource(): void
    {
        $homeowner = $this->homeowner();

        foreach (['/homeowner/dues/dues-obligations', '/homeowner/payments', '/homeowner/complaints', '/homeowner/service-requests', '/homeowner/certificates', '/homeowner/announcements'] as $path) {
            $this->actingAs($homeowner->user, 'homeowner')->get($path)->assertOk();
        }
    }

    public function test_homeowner_record_routes_cannot_resolve_another_owners_records(): void
    {
        $owner = $this->homeowner();
        $other = $this->homeowner();
        $complaint = Complaint::query()->create([
            'homeowner_id' => $other->id,
            'subject' => 'Other resident concern',
            'description' => 'This record must never be visible to another resident.',
            'category' => 'Security',
            'priority' => 'High',
            'status' => 'Pending',
        ]);
        $request = ServiceRequest::query()->create([
            'homeowner_id' => $other->id,
            'request_type' => 'Gate Pass',
            'details' => 'Private resident request.',
            'status' => 'Pending',
        ]);

        $this->actingAs($owner->user, 'homeowner')->get('/homeowner/complaints/'.$complaint->id)->assertNotFound();
        $this->actingAs($owner->user, 'homeowner')->get('/homeowner/service-requests/'.$request->id)->assertNotFound();
    }

    public function test_owner_scopes_cover_payment_dues_certificate_and_announcements(): void
    {
        $owner = $this->homeowner();
        $other = $this->homeowner();
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $setting = DuesSetting::query()->create(['name' => 'Monthly Dues', 'amount' => '100.00', 'frequency' => 'Monthly', 'is_active' => true]);
        $obligation = DuesObligation::query()->create(['homeowner_id' => $other->id, 'dues_setting_id' => $setting->id, 'billing_year' => 2026, 'billing_month' => 9, 'due_date' => '2026-09-10', 'amount_due' => '100.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending']);
        $payment = Payment::query()->create(['homeowner_id' => $other->id, 'dues_setting_id' => $setting->id, 'dues_obligation_id' => $obligation->id, 'amount_paid' => '10.00', 'penalty' => '0.00', 'payment_date' => '2026-09-01', 'covered_period' => '2026-09', 'payment_method' => 'Cash', 'recorded_by' => $admin->id]);
        Certificate::query()->create(['homeowner_id' => $other->id, 'certificate_number' => 'CERT-2026-OTHER', 'type' => 'HOA Clearance', 'status' => 'Issued', 'issued_at' => now(), 'issued_by' => $admin->id]);
        Announcement::query()->create(['title' => 'Residents only', 'content' => 'Visible to signed-in residents.', 'category' => 'General', 'audience' => 'Residents', 'status' => 'Published', 'published_at' => now(), 'created_by' => $admin->id]);

        $this->actingAs($owner->user, 'homeowner')->get('/homeowner/payments')->assertOk()->assertDontSee($payment->or_number);
        $this->actingAs($owner->user, 'homeowner')->get('/homeowner/certificates')->assertOk()->assertDontSee('CERT-2026-OTHER');
        $this->actingAs($owner->user, 'homeowner')->get('/homeowner/announcements')->assertOk()->assertSee('Residents only');
    }

    public function test_homeowner_case_views_show_outcomes_and_linked_certificate(): void
    {
        $owner = $this->homeowner();
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $complaint = Complaint::query()->create([
            'homeowner_id' => $owner->id,
            'subject' => 'Resolved drainage concern',
            'description' => 'The drainage concern has enough detail for the HOA review workflow.',
            'category' => 'Common Area',
            'priority' => 'Medium',
            'status' => 'Resolved',
            'admin_remarks' => 'Drainage channel cleared and inspected.',
            'handled_by' => $staff->id,
            'resolved_at' => now(),
        ]);
        $request = ServiceRequest::query()->create([
            'homeowner_id' => $owner->id,
            'request_type' => 'Certificate of Residency',
            'subject' => 'Residency document',
            'details' => 'Certificate requested for a school enrollment requirement.',
            'status' => 'Completed',
            'admin_remarks' => 'Approved after resident record verification.',
            'handled_by' => $staff->id,
            'completed_at' => now(),
        ]);
        $certificate = Certificate::query()->create([
            'homeowner_id' => $owner->id,
            'service_request_id' => $request->id,
            'certificate_number' => 'CERT-2026-0099',
            'type' => 'Certificate of Residency',
            'status' => 'Issued',
            'issued_at' => now(),
            'issued_by' => $staff->id,
        ]);

        $this->actingAs($owner->user, 'homeowner')
            ->get('/homeowner/complaints/'.$complaint->id)
            ->assertOk()
            ->assertSee('Drainage channel cleared and inspected.')
            ->assertSee('Status timeline');

        $this->actingAs($owner->user, 'homeowner')
            ->get('/homeowner/service-requests/'.$request->id)
            ->assertOk()
            ->assertSee('Approved after resident record verification.')
            ->assertSee('Download certificate');

        $this->assertTrue($request->certificate->is($certificate));
    }

    private function homeowner(): Homeowner
    {
        $index = User::query()->count() + 1;
        $user = User::factory()->create(['email' => "panel{$index}@example.test", 'account_status' => 'Active']);
        $user->assignRole('homeowner');

        return $user->homeowner()->create([
            'house_number' => (string) $index,
            'street' => 'Panel Street',
            'block' => "B {$index}",
            'lot' => "L {$index}",
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_number' => '09123456789',
            'status' => 'Active',
        ]);
    }
}
