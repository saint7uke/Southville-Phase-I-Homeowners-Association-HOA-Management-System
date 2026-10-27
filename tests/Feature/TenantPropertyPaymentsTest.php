<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Dues\GenerateSettingObligations;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\SubmitPaymentProof;
use App\Filament\Homeowner\Resources\Dues\Pages\ListDuesObligations;
use App\Filament\Homeowner\Resources\Payments\Pages\ListPayments;
use App\Filament\Homeowner\Widgets\HomeownerOverview;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\PaymentDocument;
use App\Models\PaymentDocumentSource;
use App\Models\User;
use App\Services\ButawReceiptEligibility;
use App\Services\PropertyDuesAccounts;
use App\Support\PropertyIdentity;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TenantPropertyPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private Homeowner $owner;

    private Homeowner $landlord;

    private Homeowner $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'Asia/Manila'));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Bus::fake();
        Storage::set('local', Storage::fake('sc07-payments-'.getmypid()));
        $this->owner = $this->resident('Owner');
        $this->landlord = $this->resident('Co-owner', ['street' => 'Alternative street spelling']);
        $this->tenant = $this->resident('Tenant', ['street' => 'Tenant street text']);
        DB::table('homeowners')->where('id', $this->tenant->id)->update(['landlord_homeowner_id' => $this->landlord->id]);
        $this->tenant->refresh();
        $this->staff = User::factory()->create(['account_status' => 'Active']);
        $this->staff->assignRole('hoa_staff');
    }

    public function test_verified_co_owner_linked_tenant_can_pay_without_transferring_liability(): void
    {
        $accounts = app(PropertyDuesAccounts::class);
        $this->assertEligible($this->tenant, true);
        $this->assertSame($this->owner->id, $accounts->primaryForAddress(PropertyIdentity::key($this->tenant))->id);
        $due = $this->obligation($this->owner);
        $payment = app(RecordPayment::class)->handle($this->payload($due), $this->staff);
        $this->assertSame($this->owner->id, $payment->homeowner_id);
        $this->assertSame($this->tenant->id, $payment->payer_homeowner_id);
        $this->assertSame($this->landlord->id, $this->tenant->fresh()->landlord_homeowner_id);
        $this->assertSame('100.25', $due->fresh()->amount_paid);
        $this->assertSame('388.75', $payment->balance);
        $this->assertSame('Cash', $payment->payment_method);
        $this->assertSame('Recorded', $payment->review_status);
        $this->assertDatabaseCount('dues_obligations', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public static function invalidLinks(): array
    {
        return [
            'unlinked tenant' => ['tenant', 'landlord_homeowner_id', null],
            'self link' => ['tenant', 'landlord_homeowner_id', 'self'],
            'tenant landlord' => ['landlord', 'ownership_type', 'Tenant'],
            'deleted landlord' => ['landlord', 'deleted_at', '2026-10-22 10:00:00'],
            'pending landlord' => ['landlord', 'status', 'Pending'],
            'inactive landlord' => ['landlord', 'status', 'Inactive'],
            'suspended landlord' => ['landlord', 'status', 'Suspended'],
            'rejected landlord' => ['landlord', 'status', 'Rejected'],
            'inactive landlord account' => ['landlord_user', 'account_status', 'Inactive'],
            'suspended landlord account' => ['landlord_user', 'account_status', 'Suspended'],
            'deleted landlord account' => ['landlord_user', 'deleted_at', '2026-10-22 10:00:00'],
            'deleted tenant' => ['tenant', 'deleted_at', '2026-10-22 10:00:00'],
            'pending tenant' => ['tenant', 'status', 'Pending'],
            'inactive tenant' => ['tenant', 'status', 'Inactive'],
            'suspended tenant' => ['tenant', 'status', 'Suspended'],
            'rejected tenant' => ['tenant', 'status', 'Rejected'],
            'inactive tenant account' => ['tenant_user', 'account_status', 'Inactive'],
            'suspended tenant account' => ['tenant_user', 'account_status', 'Suspended'],
            'deleted tenant account' => ['tenant_user', 'deleted_at', '2026-10-22 10:00:00'],
            'unknown tenant phase' => ['tenant', 'phase_suffix', null],
            'unknown landlord phase' => ['landlord', 'phase_suffix', null],
            'different tenant phase' => ['tenant', 'phase_suffix', 'B'],
            'different landlord phase' => ['landlord', 'phase_suffix', 'B'],
            'different tenant block' => ['tenant', 'block', '99'],
            'different landlord lot' => ['landlord', 'lot', '99'],
        ];
    }

    public function test_legacy_recording_reuses_the_property_assessment_and_derives_the_tenant_payer(): void
    {
        $due = $this->obligation($this->owner);
        $data = $this->payload($due);
        unset($data['dues_obligation_id'], $data['payer_homeowner_id']);
        $payment = app(RecordPayment::class)->handle([...$data, 'homeowner_id' => $this->tenant->id, 'dues_setting_id' => $due->dues_setting_id], $this->staff);
        $this->assertSame($due->id, $payment->dues_obligation_id);
        $this->assertSame($this->owner->id, $payment->homeowner_id);
        $this->assertSame($this->tenant->id, $payment->payer_homeowner_id);
        $this->assertDatabaseCount('dues_obligations', 1);
    }

    #[DataProvider('invalidLinks')]
    public function test_invalid_links_cannot_enter_the_selector_or_record_funds(string $target, string $field, mixed $value): void
    {
        $due = $this->obligation($this->owner);
        $before = $due->fresh()->getAttributes();
        $record = match ($target) {
            'tenant' => $this->tenant, 'landlord' => $this->landlord,
            'tenant_user' => $this->tenant->user, 'landlord_user' => $this->landlord->user,
        };
        $changes = [$field => $value === 'self' ? $this->tenant->id : $value];
        if (in_array($field, ['phase_suffix', 'block', 'lot'], true)) {
            $changes['property_key'] = PropertyIdentity::confirmedKey([...$record->getAttributes(), ...$changes]);
        }
        // Invalid legacy/forged fixture only; ordinary management rejects these links.
        DB::table($record->getTable())->where('id', $record->id)->update($changes);
        $this->assertEligible($this->tenant, false);
        $this->expectInvalidPayment($due);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($before, $due->fresh()->getAttributes());
    }

    public function test_stale_loaded_relationships_do_not_override_current_account_checks(): void
    {
        $this->tenant->load('landlord.user', 'user');
        $this->assertEligible($this->tenant, true);
        DB::table('users')->where('id', $this->landlord->user_id)->update(['account_status' => 'Suspended']);
        $this->assertSame('Active', $this->tenant->landlord->user->account_status);
        $this->assertEligible($this->tenant, false);
    }

    public function test_delinquency_does_not_prevent_an_active_household_from_settling_dues(): void
    {
        foreach ([$this->owner, $this->landlord, $this->tenant] as $resident) {
            $resident->update(['status' => 'Delinquent']);
        }
        $this->assertEligible($this->tenant, true);
        $payment = app(RecordPayment::class)->handle($this->payload($this->obligation($this->owner)), $this->staff);
        $this->assertSame($this->tenant->id, $payment->payer_homeowner_id);
    }

    public function test_legacy_direct_links_remain_supported_but_unknown_phase_does_not_gain_indirect_access(): void
    {
        foreach ([$this->owner, $this->landlord, $this->tenant] as $resident) {
            DB::table('homeowners')->where('id', $resident->id)->update(['phase_suffix' => null, 'property_key' => null, 'street' => 'Legacy street', 'address_key' => $this->owner->address_key]);
        }
        $this->owner->refresh();
        $this->assertEligible($this->tenant, false);
        DB::table('homeowners')->where('id', $this->tenant->id)->update(['landlord_homeowner_id' => $this->owner->id]);
        $this->assertEligible($this->tenant, true);
    }

    public function test_unrelated_linked_tenant_and_unlinked_same_property_resident_remain_ineligible(): void
    {
        $otherOwner = $this->resident('Owner', ['block' => '99']);
        $otherTenant = $this->resident('Tenant', ['block' => '99', 'landlord_homeowner_id' => $otherOwner->id]);
        $unlinked = $this->resident('Tenant');
        $this->assertEligible($otherTenant, false);
        $this->assertEligible($unlinked, false);
        $this->assertEligible($this->landlord, true);
        $this->assertEligible($this->owner, true);
    }

    public function test_both_payment_forms_offer_the_tenant_and_reject_a_link_removed_before_submission(): void
    {
        $due = $this->obligation($this->owner);
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        foreach ([[$admin, 'admin'], [$this->staff, 'staff']] as [$actor, $panel]) {
            $this->login($actor, $panel);
            $component = Livewire::test(CreatePayment::class)->fillForm($this->payload($due));
            $component->assertFormFieldExists('payer_homeowner_id', fn (Select $field): bool => array_key_exists($this->tenant->id, $field->getOptions()));
        }
        $this->tenant->update(['landlord_homeowner_id' => null]);
        $component->call('create')->assertHasFormErrors(['payer_homeowner_id']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('0.00', $due->fresh()->amount_paid);
    }

    public function test_staff_form_records_the_co_owner_linked_payer_successfully(): void
    {
        $due = $this->obligation($this->owner);
        $this->login($this->staff, 'staff');
        Livewire::test(CreatePayment::class)->fillForm($this->payload($due))->call('create')
            ->assertHasNoFormErrors()->assertNotified('Payment recorded successfully');
        $this->assertDatabaseHas('payments', ['homeowner_id' => $this->owner->id, 'payer_homeowner_id' => $this->tenant->id, 'review_status' => 'Recorded']);
        $this->assertDatabaseCount('dues_obligations', 1);
    }

    public function test_eligible_tenant_sees_only_property_dues_and_not_other_residents_payment_history(): void
    {
        $due = $this->obligation($this->owner);
        $otherDue = $this->obligation($this->resident('Owner', ['block' => '99']));
        $otherPayment = app(RecordPayment::class)->handle([...$this->payload($due), 'payer_homeowner_id' => $this->owner->id], $this->staff);
        $this->login($this->tenant->user, 'homeowner');
        $this->assertTrue(Gate::forUser($this->tenant->user)->allows('view', $due));
        $this->assertFalse(Gate::forUser($this->tenant->user)->allows('view', $otherDue));
        Livewire::test(ListDuesObligations::class)->assertCanSeeTableRecords([$due])->assertCanNotSeeTableRecords([$otherDue]);
        Livewire::test(HomeownerOverview::class)->assertSee('PHP 388.75');
        Livewire::test(ListPayments::class)->assertCanNotSeeTableRecords([$otherPayment]);
        $this->assertFalse(Gate::forUser($this->tenant->user)->allows('view', $otherPayment));
        $this->login($this->tenant->user, 'web');
        $this->get(route('portal.dashboard'))->assertOk()->assertViewHas('duesObligations', fn ($dues) => $dues->modelKeys() === [$due->id]);
    }

    public function test_receipts_remain_private_to_the_liable_account_actual_payer_and_managers(): void
    {
        $due = $this->obligation($this->owner);
        $payment = app(RecordPayment::class)->handle($this->payload($due), $this->staff);
        $otherTenant = $this->resident('Tenant', ['street' => $this->landlord->street, 'landlord_homeowner_id' => $this->landlord->id]);
        $document = $this->document($payment);
        foreach ([$this->tenant->user, $this->owner->user, $this->staff] as $allowed) {
            $this->login($allowed);
            foreach ([route('payments.receipt.download', $payment), route('certificates.butaw.download', $document)] as $url) {
                $this->assertSame(Storage::get($document->path), $this->get($url)->assertOk()->streamedContent());
            }
        }
        foreach ([$this->landlord->user, $otherTenant->user] as $denied) {
            $this->login($denied);
            $this->get(route('payments.receipt.download', $payment))->assertNotFound();
            $this->get(route('certificates.butaw.download', $document))->assertNotFound();
        }
        $this->login($this->tenant->user, 'homeowner');
        Livewire::test(ListPayments::class)->assertCanSeeTableRecords([$payment]);
        $this->tenant->update(['landlord_homeowner_id' => null]);
        $this->assertEligible($this->tenant, false);
        $this->assertFalse(Gate::forUser($this->tenant->user)->allows('view', $due));
        $this->login($this->tenant->user);
        $this->get(route('certificates.butaw.download', $document))->assertOk();
        $this->tenant->user->forceFill(['account_status' => 'Inactive'])->saveQuietly();
        $this->login($this->tenant->user);
        $this->get(route('certificates.butaw.download', $document))->assertForbidden();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('dues_obligations', 1);
    }

    public function test_cash_only_and_disabled_electronic_proofs_are_not_relaxed(): void
    {
        $due = $this->obligation($this->owner);
        try {
            app(RecordPayment::class)->handle([...$this->payload($due), 'payment_method' => 'GCash'], $this->staff);
            $this->fail('Only Cash may be recorded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->expectException(AuthorizationException::class);
        app(SubmitPaymentProof::class)->handle($this->tenant->user, $due, [], UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
    }

    public function test_payment_eligibility_does_not_grant_a_tenant_staff_recording_permission(): void
    {
        $due = $this->obligation($this->owner);
        $this->assertEligible($this->tenant, true);
        $this->expectException(AuthorizationException::class);
        app(RecordPayment::class)->handle($this->payload($due), $this->tenant->user);
    }

    public function test_generation_still_creates_one_assessment_and_uses_the_co_owner_only_as_fallback(): void
    {
        $setting = DuesSetting::create(['name' => 'SC07 generation', 'amount' => '489.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);
        $generator = app(GenerateSettingObligations::class);
        $this->assertSame(1, $generator->handle($setting, CarbonImmutable::parse('2026-10-01')));
        $this->assertSame(0, $generator->handle($setting, CarbonImmutable::parse('2026-10-01')));
        $this->assertSame($this->owner->id, DuesObligation::sole()->homeowner_id);
        $this->owner->delete();
        $this->assertSame($this->landlord->id, app(PropertyDuesAccounts::class)->primaryForAddress(PropertyIdentity::key($this->landlord))->id);
        $this->assertTrue(app(PropertyDuesAccounts::class)->payerIsEligible($this->tenant, $this->landlord));
        $this->assertSame(1, $generator->handle($setting, CarbonImmutable::parse('2026-11-01')));
        $this->assertDatabaseCount('dues_obligations', 2);
    }

    private function resident(string $type, array $overrides = []): Homeowner
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');

        return $user->homeowner()->create([...['phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'block' => '1', 'lot' => '2', 'street' => 'Synthetic street', 'residency_date' => '2020-01-01', 'ownership_type' => $type, 'status' => 'Active'], ...$overrides])->load('user');
    }

    private function obligation(Homeowner $liable): DuesObligation
    {
        $setting = DuesSetting::create(['name' => 'SC07 dues '.Str::random(8), 'amount' => '489.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);

        return DuesObligation::create(['homeowner_id' => $liable->id, 'dues_setting_id' => $setting->id, 'billing_year' => 2026, 'billing_month' => 10, 'due_date' => '2026-10-31', 'amount_due' => '489.00', 'amount_paid' => '0.00', 'penalty_amount' => '0.00', 'status' => 'Pending']);
    }

    private function payload(DuesObligation $due): array
    {
        return ['dues_obligation_id' => $due->id, 'payer_homeowner_id' => $this->tenant->id, 'amount_paid' => '100.25', 'penalty' => '0.00', 'payment_method' => 'Cash', 'payment_date' => '2026-10-23'];
    }

    private function assertEligible(Homeowner $payer, bool $expected): void
    {
        $accounts = app(PropertyDuesAccounts::class);
        $this->assertSame($expected, $accounts->payerIsEligible($payer, $this->owner));
        $this->assertSame($expected, $accounts->eligiblePayers($this->owner)->whereKey($payer->id)->exists());
    }

    private function expectInvalidPayment(DuesObligation $due): void
    {
        try {
            app(RecordPayment::class)->handle($this->payload($due), $this->staff);
            $this->fail('Invalid tenant eligibility must not record funds.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payer_homeowner_id', $exception->errors());
        }
    }

    private function login(User $user, string $guard = 'web'): void
    {
        foreach (['admin', 'staff', 'homeowner', 'web'] as $name) {
            Auth::guard($name)->logout();
        }
        Filament::setCurrentPanel(in_array($guard, ['admin', 'staff', 'homeowner'], true) ? $guard : null);
        $this->actingAs($user, $guard);
    }

    private function document(Payment $payment): PaymentDocument
    {
        // Private synthetic saved-file fixture, not a real source approval or rendered receipt.
        $bytes = '%PDF-1.4 SYNTHETIC SC07 RECEIPT '.Str::uuid();
        $source = PaymentDocumentSource::create(['source_checksum' => hash('sha256', $bytes), 'manifest' => ['font_profile' => 'butaw'], 'approved_by' => $this->staff->id, 'approved_at' => now(), 'approval_note' => 'Synthetic test fixture only.']);
        $path = 'payment-documents/'.Str::uuid().'.pdf';
        Storage::put($path, $bytes);

        return PaymentDocument::create(['payment_id' => $payment->id, 'payment_document_source_id' => $source->id, 'source_checksum' => $source->source_checksum, 'snapshot' => app(ButawReceiptEligibility::class)->snapshot($payment), 'path' => $path, 'file_checksum' => hash('sha256', $bytes), 'generated_by' => $this->staff->id]);
    }
}
