<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Dues\GenerateCurrentMonthDues;
use App\Actions\Dues\GenerateSettingObligations;
use App\Actions\Dues\SaveDuesSetting;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPaymentProof;
use App\Actions\Payments\SubmitPaymentProof;
use App\Actions\Payments\UpdatePaymentMetadata;
use App\Filament\Resources\DuesSettings\Pages\CreateDuesSetting;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReviewCompleted;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\LegacyPaymentProof;
use Tests\TestCase;

final class DuesAndPaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_new_electronic_recording_is_rejected_but_cash_still_works(): void
    {
        $due = $this->obligation($this->resident('Owner', '60', 'Cash Street'), '489.00');
        $actor = $this->staff();
        foreach (['GCash', 'Maya', 'Bank Transfer'] as $method) {
            try {
                app(RecordPayment::class)->handle([
                    'dues_obligation_id' => $due->id, 'amount_paid' => '100.00',
                    'payment_date' => today()->toDateString(), 'payment_method' => $method,
                    'transaction_reference' => 'FORGED-'.Str::uuid(),
                ], $actor);
                $this->fail('Non-cash recording must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment_method', $exception->errors());
            }
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('0.00', $due->fresh()->amount_paid);
        $payment = app(RecordPayment::class)->handle([
            'dues_obligation_id' => $due->id, 'amount_paid' => '100.00',
            'payment_date' => today()->toDateString(), 'payment_method' => 'Cash',
        ], $actor);
        $this->assertSame('Cash', $payment->payment_method);
        $this->assertSame('389.00', $payment->balance);
    }

    public function test_current_month_recovery_preserves_existing_amounts_and_is_idempotent(): void
    {
        $owner = $this->resident('Owner', '61', 'Recovery Street');
        $due = $this->obligation($owner, '489.00');
        $due->update(['billing_year' => today()->year, 'billing_month' => today()->month]);
        $due->duesSetting->update(['amount' => '500.00']);
        $action = app(GenerateCurrentMonthDues::class);
        $this->assertSame(0, $action->handle($this->staff()));
        $this->assertSame('489.00', $due->fresh()->amount_due);
        $this->resident('Owner', '62', 'Recovery Street');
        $this->assertSame(1, $action->handle($this->staff()));
        $this->assertSame(0, $action->handle($this->staff()));
        $this->assertDatabaseCount('dues_obligations', 2);
        $this->expectException(AuthorizationException::class);
        $action->handle($owner->user);
    }

    public function test_payment_method_and_reference_are_immutable_during_metadata_updates(): void
    {
        $actor = $this->staff('hoa_admin');
        $due = $this->obligation($this->resident('Owner', '63', 'History Street'), '100.00');
        $payment = Payment::query()->create([
            'homeowner_id' => $due->homeowner_id, 'dues_setting_id' => $due->dues_setting_id,
            'dues_obligation_id' => $due->id, 'amount_paid' => '10.00', 'penalty' => '0.00',
            'payment_date' => today(), 'payment_method' => 'GCash', 'transaction_reference' => 'HISTORIC',
            'transaction_reference_key' => 'HISTORIC', 'status' => 'Partial', 'review_status' => 'Recorded',
            'covered_period' => 'October 2026',
            'recorded_by' => $actor->id,
        ]);
        $action = app(UpdatePaymentMetadata::class);
        $action->handle($payment, ['payment_date' => today()->toDateString(), 'notes' => 'Reviewed historic entry'], $actor);
        $this->assertSame('GCash', $payment->fresh()->payment_method);
        $this->assertSame('HISTORIC', $payment->fresh()->transaction_reference);
        $this->expectException(ValidationException::class);
        $action->handle($payment, ['payment_date' => today()->toDateString(), 'payment_method' => 'Cash'], $actor);
    }

    public function test_new_proof_is_rejected_through_both_domain_and_portal_entrypoints(): void
    {
        $owner = $this->resident('Owner', '64', 'Proof Disabled Street');
        $due = $this->obligation($owner, '100.00');
        $this->actingAs($owner->user, 'web')->post(route('portal.dues-obligations.proof.store', $due), [
            'amount_paid' => '10.00', 'payment_date' => today()->toDateString(),
            'payment_method' => 'GCash', 'transaction_reference' => 'NEW-PROOF',
            'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
        $this->expectException(AuthorizationException::class);
        app(SubmitPaymentProof::class)->handle($owner->user, $due, [], UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
    }

    public function test_cash_form_and_confirmed_recovery_action_are_available_without_terminal_instructions(): void
    {
        $admin = $this->staff('hoa_admin');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $owner = $this->resident('Owner', '65', 'Recovery Interface Street');
        $this->obligation($owner, '489.00');

        Livewire::test(CreatePayment::class)
            ->assertFormSet(['payment_method' => 'Cash'])
            ->assertFormFieldExists('payment_method', fn (TextInput $field): bool => $field->isReadOnly())
            ->assertDontSee('php artisan')
            ->callAction('generateCurrentDues')
            ->assertNotified('No new dues were needed.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'Dues.current_month_generated', 'user_id' => $admin->id]);
    }

    public function test_equivalent_dues_names_cannot_overlap_but_can_follow_a_closed_period(): void
    {
        $staff = $this->staff();
        $action = app(SaveDuesSetting::class);
        $first = $action->create($this->schedule(['name' => ' Monthly   Association Dues ', 'ends_on' => '2026-06-30']), $staff);

        $this->assertSame('monthly association dues', $first->normalized_name);
        try {
            $action->create($this->schedule(['name' => 'MONTHLY ASSOCIATION DUES', 'starts_on' => '2026-06-01']), $staff);
            $this->fail('Overlapping normalized schedules must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $replacement = $action->create($this->schedule(['name' => 'monthly association dues', 'starts_on' => '2026-07-01']), $staff);
        $this->assertSame('2026-07-01', $replacement->starts_on->toDateString());
    }

    public function test_obligations_are_generated_once_per_property_with_owner_preference(): void
    {
        $owner = $this->resident('Owner', '14', 'Shared Street');
        $this->resident('Co-owner', '14', 'Shared Street');
        $tenant = $this->resident('Tenant', '14', 'Shared Street', $owner);
        $setting = DuesSetting::query()->create([
            'name' => 'Property Dues', 'amount' => '500.00', 'frequency' => 'Monthly',
            'starts_on' => '2026-01-01', 'is_active' => true,
        ]);

        $this->assertSame(1, app(GenerateSettingObligations::class)->handle($setting, CarbonImmutable::create(2026, 10, 1)));
        $this->assertDatabaseHas('dues_obligations', ['homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id]);
        $this->assertDatabaseMissing('dues_obligations', ['homeowner_id' => $tenant->id, 'dues_setting_id' => $setting->id]);
    }

    public function test_linked_tenant_can_pay_owner_obligation_and_idempotent_token_prevents_duplicates(): void
    {
        $staff = $this->staff();
        $owner = $this->resident('Owner', '22', 'Pay Street');
        $tenant = $this->resident('Tenant', '22', 'Pay Street', $owner);
        $obligation = $this->obligation($owner, '200.00');
        $token = (string) Str::uuid();
        $payload = [
            'dues_obligation_id' => $obligation->id,
            'payer_homeowner_id' => $tenant->id,
            'amount_paid' => '75.00',
            'penalty' => '0.00',
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Cash',
            'submission_token' => $token,
        ];

        $first = app(RecordPayment::class)->handle($payload, $staff);
        $retry = app(RecordPayment::class)->handle($payload, $staff);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame($owner->id, $first->homeowner_id);
        $this->assertSame($tenant->id, $first->payer_homeowner_id);
        $this->assertSame(1, Payment::query()->count());

        $payload['submission_token'] = (string) Str::uuid();
        $payload['amount_paid'] = '126.00';
        $this->expectException(ValidationException::class);
        app(RecordPayment::class)->handle($payload, $staff);
    }

    public function test_pending_proofs_reserve_balance_and_rejection_requires_reason_and_notifies_once_per_user(): void
    {
        Storage::fake('local');
        Notification::fake();
        $owner = $this->resident('Owner', '30', 'Proof Street');
        $obligation = $this->obligation($owner, '100.00');
        $payment = LegacyPaymentProof::create($owner->user, $obligation, [
            'amount_paid' => '80.00',
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Maya',
            'transaction_reference' => 'MAYA-ABC-1',
        ], UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));

        try {
            app(RecordPayment::class)->handle([
                'dues_obligation_id' => $obligation->id,
                'amount_paid' => '21.00', 'payment_date' => today()->toDateString(),
                'payment_method' => 'Cash',
            ], $this->staff());
            $this->fail('Pending proofs must reserve the outstanding balance.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_paid', $exception->errors());
        }

        $admin = $this->staff('hoa_admin');
        try {
            app(ReviewPaymentProof::class)->reject($payment, $admin, '');
            $this->fail('A rejection reason is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('review_notes', $exception->errors());
        }

        $rejected = app(ReviewPaymentProof::class)->reject($payment, $admin, 'The uploaded reference could not be verified.');
        $this->assertSame('Rejected', $rejected->review_status);
        $this->assertSame('The uploaded reference could not be verified.', $rejected->review_notes);
        Notification::assertSentToTimes($owner->user, PaymentReviewCompleted::class, 1);
    }

    public function test_filament_dues_and_payment_creation_use_authoritative_actions(): void
    {
        $admin = $this->staff('hoa_admin');
        $owner = $this->resident('Owner', '45', 'Filament Street');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateDuesSetting::class)
            ->fillForm($this->schedule(['name' => 'Livewire Dues', 'starts_on' => today()->startOfMonth()->toDateString()]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Dues schedule created successfully');

        $setting = DuesSetting::query()->where('normalized_name', 'livewire dues')->sole();
        $obligation = DuesObligation::query()->firstOrCreate([
            'homeowner_id' => $owner->id,
            'dues_setting_id' => $setting->id,
            'billing_year' => (int) today()->year,
            'billing_month' => (int) today()->month,
        ], [
            'due_date' => today()->endOfMonth(),
            'amount_due' => $setting->amount,
            'penalty_amount' => '0.00',
            'amount_paid' => '0.00',
            'status' => 'Pending',
        ]);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'dues_obligation_id' => $obligation->id,
                'payer_homeowner_id' => $owner->id,
                'amount_paid' => '100.00',
                'penalty' => '0.00',
                'payment_date' => today()->toDateString(),
                'payment_method' => 'Cash',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Payment recorded successfully');

        $this->assertDatabaseHas('payments', [
            'homeowner_id' => $owner->id,
            'payer_homeowner_id' => $owner->id,
            'dues_obligation_id' => $obligation->id,
            'review_status' => 'Recorded',
        ]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function schedule(array $overrides = []): array
    {
        return [...[
            'name' => 'Association Dues', 'amount' => '500.00', 'frequency' => 'Monthly',
            'due_day' => 10, 'starts_on' => '2026-01-01', 'ends_on' => null,
            'description' => null, 'is_active' => true,
        ], ...$overrides];
    }

    private function staff(string $role = 'hoa_staff'): User
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole($role);

        return $user;
    }

    private function resident(string $type, string $house, string $street, ?Homeowner $landlord = null): Homeowner
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');

        return Homeowner::query()->create([
            'user_id' => $user->id, 'house_number' => $house, 'street' => $street,
            'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01',
            'ownership_type' => $type, 'status' => 'Active', 'landlord_homeowner_id' => $landlord?->id,
        ])->load('user');
    }

    private function obligation(Homeowner $owner, string $amount): DuesObligation
    {
        $setting = DuesSetting::query()->create([
            'name' => 'Dues '.Str::random(8), 'amount' => $amount, 'frequency' => 'Monthly',
            'starts_on' => '2026-01-01', 'is_active' => true,
        ]);

        return DuesObligation::query()->create([
            'homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id,
            'billing_year' => 2026, 'billing_month' => 10, 'due_date' => '2026-10-31',
            'amount_due' => $amount, 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending',
        ]);
    }
}
