<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Dues\GenerateSettingObligations;
use App\Actions\Homeowners\ConfirmPropertyPhase;
use App\Actions\Homeowners\RegisterHomeowner;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\User;
use App\Services\LandlordMatcher;
use App\Services\PropertyAddressAvailability;
use App\Services\PropertyDuesAccounts;
use App\Support\PropertyIdentity;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ConfirmedPropertyIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    public function test_identity_ignores_house_and_street_but_not_suffix(): void
    {
        $this->assertSame(PropertyIdentity::key($this->address()), PropertyIdentity::key([...$this->address(), 'house_number' => '99', 'street' => 'Another Road', 'block' => 'Block 7', 'lot' => 'Lot 8', 'phase_suffix' => ' a ']));
        $this->assertNotSame(PropertyIdentity::key($this->address()), PropertyIdentity::key([...$this->address(), 'phase_suffix' => 'B']));
        $this->assertSame('Phase 1 - A', PropertyIdentity::phase($this->address()));
    }

    public function test_owner_duplicates_and_sharing_use_confirmed_property(): void
    {
        $this->resident();
        $service = app(PropertyAddressAvailability::class);
        $this->assertFalse($service->available([...$this->address(), 'ownership_type' => 'Owner', 'street' => 'Different spelling']));
        foreach (['Tenant', 'Co-owner'] as $occupancy) {
            $this->assertTrue($service->available([...$this->address(), 'ownership_type' => $occupancy]));
        }
        $this->assertTrue($service->available([...$this->address(), 'ownership_type' => 'Owner', 'phase_suffix' => 'B']));
    }

    public function test_unknown_legacy_phase_blocks_all_new_claims_and_landlord_linking(): void
    {
        $owner = $this->resident(['phase_suffix' => null]);
        foreach (['Owner', 'Tenant', 'Co-owner'] as $occupancy) {
            $this->postJson(route('homeowner.register.address-availability'), [...$this->address(), 'ownership_type' => $occupancy])
                ->assertExactJson(['available' => false, 'message' => PropertyAddressAvailability::REVIEW_MESSAGE]);
        }
        $this->postJson(route('homeowner.register.landlord-availability'), [...$this->address(), 'landlord_name' => $owner->user->full_name])
            ->assertExactJson(['valid' => false, 'message' => PropertyAddressAvailability::REVIEW_MESSAGE]);
        $this->assertNull($owner->fresh()->phase_suffix);
    }

    public function test_registration_without_house_number_persists_uppercase_suffix(): void
    {
        $user = app(RegisterHomeowner::class)->handle([
            ...$this->address(), 'phase_suffix' => ' a ', 'first_name' => 'Test', 'last_name' => 'Resident',
            'sex' => 'Male', 'date_of_birth' => '1990-01-01', 'contact_number' => '09171234567',
            'email' => 'phase-new@example.test', 'password' => 'ResidentPass!2026', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01',
        ]);
        $this->assertNull($user->homeowner->house_number);
        $this->assertSame('A', $user->homeowner->phase_suffix);
        $this->assertSame(PropertyIdentity::key($this->address()), $user->homeowner->property_key);
    }

    public function test_forged_house_number_is_rejected_by_endpoint_and_action(): void
    {
        $this->postJson(route('homeowner.register.address-availability'), [...$this->address(), 'ownership_type' => 'Owner', 'house_number' => 'invented'])
            ->assertUnprocessable()->assertJsonValidationErrors('house_number');
        $this->expectException(ValidationException::class);
        app(RegisterHomeowner::class)->handle([...$this->address(), 'house_number' => 'invented']);
    }

    public function test_forged_nonempty_house_number_array_is_not_normalized_away(): void
    {
        $this->post(route('homeowner.register.store'), [
            ...$this->address(), 'house_number' => ['12'], 'first_name' => 'Test', 'last_name' => 'Resident',
            'sex' => 'Male', 'date_of_birth' => '1990-01-01', 'contact_number' => '09171234567',
            'email' => 'forged-array@example.test', 'password' => 'ResidentPass!2026', 'password_confirmation' => 'ResidentPass!2026',
            'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'privacy_consent' => '1',
        ])->assertSessionHasErrors('house_number');
        $this->assertDatabaseMissing('users', ['email' => 'forged-array@example.test']);
    }

    public function test_landlord_and_payer_matching_do_not_cross_confirmed_phases(): void
    {
        $owner = $this->resident();
        $coowner = $this->resident(['ownership_type' => 'Co-owner', 'street' => 'Narra St.']);
        $other = $this->resident(['phase_suffix' => 'B', 'ownership_type' => 'Co-owner']);
        $accounts = app(PropertyDuesAccounts::class);
        $this->assertTrue($accounts->payerIsEligible($coowner, $owner));
        $this->assertFalse($accounts->payerIsEligible($other, $owner));
        $this->assertSame($owner->id, $accounts->primaryForAddress(PropertyIdentity::key($owner))->id);
        $this->assertTrue(app(LandlordMatcher::class)->match([...$this->address(), 'landlord_name' => $owner->user->full_name])['valid']);
        $this->assertFalse(app(LandlordMatcher::class)->match([...$this->address(), 'phase_suffix' => 'C', 'landlord_name' => $owner->user->full_name])['valid']);
    }

    public function test_authorized_confirmation_does_not_rewrite_legacy_address_or_other_accounts(): void
    {
        $owner = $this->resident(['phase_suffix' => null]);
        $legacyKey = $owner->address_key;
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        app(ConfirmPropertyPhase::class)->handle($owner, ' a ', $admin);
        $this->assertSame('A', $owner->fresh()->phase_suffix);
        $this->assertSame($legacyKey, $owner->fresh()->address_key);
    }

    public function test_registration_markup_has_no_house_number_and_icon_rules_exclude_label_text(): void
    {
        $this->get(route('homeowner.register'))->assertOk()->assertDontSee('name="house_number"', false)
            ->assertSee('name="phase_suffix"', false)->assertDontSee('.hoa-field label span {', false)
            ->assertSee('li > span[aria-hidden="true"]', false);
    }

    public function test_future_generation_groups_confirmed_properties_and_preserves_existing_assessments(): void
    {
        $owner = $this->resident(['phase_suffix' => null, 'house_number' => '12']);
        $schedule = DuesSetting::create(['name' => 'Identity dues', 'amount' => '489.00', 'frequency' => 'Monthly', 'starts_on' => today()->startOfMonth(), 'is_active' => true]);
        $generator = app(GenerateSettingObligations::class);
        $period = CarbonImmutable::now()->startOfMonth();
        $this->assertSame(1, $generator->handle($schedule, $period));
        $assessment = DuesObligation::firstOrFail();
        $historical = $assessment->getAttributes();
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        app(ConfirmPropertyPhase::class)->handle($owner, 'A', $admin);
        $this->resident(['ownership_type' => 'Co-owner', 'house_number' => '99', 'street' => 'Different Street']);
        $this->assertSame(0, $generator->handle($schedule, $period));
        $this->assertSame($historical, $assessment->fresh()->getAttributes());
        $this->resident(['phase_suffix' => 'B']);
        $this->assertSame(1, $generator->handle($schedule, $period));
        $this->assertSame(2, DuesObligation::count());
    }

    public function test_current_period_liable_account_cannot_silently_move_its_assessment_to_a_different_property(): void
    {
        $owner = $this->resident();
        $schedule = DuesSetting::create(['name' => 'Move review dues', 'amount' => '489.00', 'frequency' => 'Monthly', 'starts_on' => today()->startOfMonth(), 'is_active' => true]);
        app(GenerateSettingObligations::class)->handle($schedule, CarbonImmutable::now()->startOfMonth());
        $assessment = DuesObligation::firstOrFail()->getAttributes();
        $owner->update(['street' => 'Corrected street spelling']);
        try {
            $owner->update(['block' => '99']);
            $this->fail('A billed liable account move requires HOA assessment review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('address', $exception->errors());
        }
        $this->assertSame('7', $owner->fresh()->block);
        $this->assertSame($assessment, DuesObligation::firstOrFail()->getAttributes());
    }

    public function test_archived_unconfirmed_assessment_requires_review_without_blocking_registration_or_rewriting_history(): void
    {
        $legacy = $this->resident(['phase_suffix' => null]);
        $schedule = DuesSetting::create(['name' => 'Archived review dues', 'amount' => '489.00', 'frequency' => 'Monthly', 'starts_on' => today()->startOfMonth(), 'is_active' => true]);
        $generator = app(GenerateSettingObligations::class);
        $period = CarbonImmutable::now()->startOfMonth();
        $this->assertSame(1, $generator->handle($schedule, $period));
        $original = DuesObligation::firstOrFail()->getAttributes();
        $legacy->delete();
        $this->assertTrue(app(PropertyAddressAvailability::class)->available([...$this->address(), 'ownership_type' => 'Owner']));
        $replacement = $this->resident();
        try {
            $generator->handle($schedule, $period);
            $this->fail('An archived unresolved assessment must not produce a second bill.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phase_suffix', $exception->errors());
        }
        $this->assertSame(1, DuesObligation::count());
        $this->assertSame($original, DuesObligation::firstOrFail()->getAttributes());
        $this->assertSame('Active', $replacement->fresh()->status);
        $this->artisan('hoa:review-property-identities', ['--json' => true])->expectsOutputToContain('Unconfirmed current-period assessment')->assertSuccessful();
    }

    private function address(): array
    {
        return ['phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'street' => 'Narra Street', 'block' => '7', 'lot' => '8'];
    }

    private function resident(array $changes = []): Homeowner
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');

        return $user->homeowner()->create([...$this->address(), 'house_number' => null, 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active', ...$changes]);
    }
}
