<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Homeowner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class VerificationDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_a_private_verification_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('verification_documents/application.pdf', '%PDF-test');

        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $homeowner = $this->createHomeowner('verification_documents/application.pdf');

        $this->actingAs($admin, 'admin')
            ->get(route('homeowners.verification-document', $homeowner))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="application.pdf"')
            ->assertSee('%PDF-test');
    }

    public function test_staff_can_view_a_private_verification_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('verification_documents/application.pdf', '%PDF-test');

        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $homeowner = $this->createHomeowner('verification_documents/application.pdf');

        $this->actingAs($staff, 'staff')
            ->get(route('homeowners.verification-document', $homeowner))
            ->assertOk();
    }

    public function test_homeowner_cannot_view_a_verification_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('verification_documents/application.pdf', '%PDF-test');

        $homeowner = $this->createHomeowner('verification_documents/application.pdf');

        $this->actingAs($homeowner->user, 'homeowner')
            ->get(route('homeowners.verification-document', $homeowner))
            ->assertNotFound();
    }

    public function test_missing_or_unsafe_verification_documents_return_not_found(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');

        $missing = $this->createHomeowner('verification_documents/missing.pdf');
        $unsafe = $this->createHomeowner('verification_documents/../private.pdf');

        $this->actingAs($admin, 'admin')
            ->get(route('homeowners.verification-document', $missing))
            ->assertNotFound();
        $this->get(route('homeowners.verification-document', $unsafe))
            ->assertNotFound();
    }

    private function createHomeowner(string $documentPath): Homeowner
    {
        $user = User::factory()->create();
        $user->assignRole('homeowner');

        return $user->homeowner()->create([
            'house_number' => '1',
            'street' => 'Narra Street',
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'status' => 'Inactive',
            'verification_document_path' => $documentPath,
        ]);
    }
}
