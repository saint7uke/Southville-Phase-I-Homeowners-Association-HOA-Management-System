<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\RecordPayment;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\PaymentDocument;
use App\Models\PaymentDocumentSource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ButawReceiptEligibility;
use App\Services\ButawReceiptSource;
use App\Services\PaymentReceiptResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SharedPaymentReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 21));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::set('local', Storage::fake('shared-receipts-'.getmypid()));
        $this->admin = User::factory()->create(['account_status' => 'Active']);
        $this->admin->assignRole('hoa_admin');
    }

    public function test_both_routes_and_dispositions_serve_the_exact_same_saved_bytes_without_generation(): void
    {
        $payment = $this->payment();
        $document = $this->document($payment);
        $before = $payment->duesObligation->getAttributes();
        // Stored files remain available without a generation font or current source assets.
        config(['certificates.butaw_font' => 'unavailable.ttf', 'certificates.butaw_source_directory' => 'unavailable-source']);
        $this->login($this->admin, 'admin');
        foreach ([route('payments.receipt.download', $payment), route('certificates.butaw.download', $document)] as $url) {
            foreach ([false, true] as $inline) {
                $response = $this->get($url.($inline ? '?view=1' : ''))->assertOk()
                    ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
                $this->assertSame(Storage::disk('local')->get($document->path), $response->streamedContent());
                $this->assertStringContainsString(($inline ? 'inline' : 'attachment').'; filename=resibo-butaw-'.$document->id.'.pdf', $response->headers->get('Content-Disposition'));
            }
        }
        $this->assertDatabaseCount('payment_document_previews', 0);
        $this->assertDatabaseCount('payment_documents', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($before, $payment->duesObligation->fresh()->getAttributes());
    }

    public function test_latest_selection_uses_timestamp_then_id_and_history_keeps_older_files(): void
    {
        $payment = $this->payment();
        $first = $this->document($payment);
        $older = $this->document($payment, now()->subDay());
        $this->assertSame($first->id, $payment->fresh()->latestReceiptDocument->id);
        $latest = $this->document($payment);
        $this->assertSame($latest->id, $payment->fresh()->latestReceiptDocument->id);
        $this->login($this->admin, 'admin');
        $this->assertSame(Storage::disk('local')->get($latest->path), $this->get(route('payments.receipt.download', $payment))->assertOk()->streamedContent());
        $this->assertSame(Storage::disk('local')->get($older->path), $this->get(route('certificates.butaw.download', $older))->assertOk()->streamedContent());
        Filament::setCurrentPanel('admin');
        Livewire::test(ListCertificates::class)->mountAction('findPaymentReceipt')
            ->assertSchemaComponentExists('document_id', null, fn ($field) => str_contains($field->getOptions()[$latest->id], 'Current receipt')
                && str_contains($field->getOptions()[$first->id], 'Historical version'));
        $this->assertDatabaseCount('payment_documents', 3);
    }

    public function test_missing_original_receipt_get_redirects_staff_to_preview_but_never_creates_a_document(): void
    {
        if (! is_file((string) config('certificates.butaw_font'))) {
            $this->markTestSkipped('A ready-generation redirect requires the licensed Calibri font.');
        }
        $payment = $this->payment();
        $this->readySource();
        $this->login($this->admin, 'admin');
        $response = $this->get(route('payments.receipt.download', $payment))->assertRedirect();
        $this->assertStringContainsString('document=butaw', $response->headers->get('Location'));
        $this->assertStringContainsString('payment='.$payment->id, $response->headers->get('Location'));
        $this->login($payment->homeowner->user);
        $this->get(route('payments.receipt.download', $payment))->assertStatus(409)->assertSee('The HOA has not generated this monthly receipt yet.');
        $this->assertDatabaseCount('payment_document_previews', 0);
        $this->assertDatabaseCount('payment_documents', 0);
        $this->assertSame('100.00', $payment->duesObligation->fresh()->amount_paid);
    }

    public function test_payment_shortcut_derives_homeowner_rejects_invalid_inputs_and_keeps_request_precedence(): void
    {
        $payment = $this->payment();
        $this->login($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::withQueryParams(['document' => 'butaw', 'payment' => $payment->id, 'homeowner' => 999999])->test(CreateCertificate::class)
            ->assertSet('data.receipt_homeowner_id', $payment->homeowner_id)->assertSet('data.payment_id', $payment->id)->assertSet('previewToken', null);
        foreach (['abc', '999999', '-1', ['1']] as $id) {
            Livewire::withQueryParams(['document' => 'butaw', 'payment' => $id])->test(CreateCertificate::class)
                ->assertHasErrors(['data.payment_id'])->assertSet('data.receipt_homeowner_id', null);
        }
        $request = ServiceRequest::create(['homeowner_id' => $payment->homeowner_id, 'request_type' => 'Certificate of Residency', 'subject' => 'Synthetic approved request', 'details' => 'Registered residency', 'status' => 'Approved']);
        Livewire::withQueryParams(['request' => $request->id, 'document' => 'butaw', 'payment' => 'forged'])->test(CreateCertificate::class)
            ->assertSet('sourceRequestId', $request->id)->assertSet('data.type', 'Certificate of Residency');
        $this->assertDatabaseCount('payment_document_previews', 0);
        $this->assertDatabaseCount('payment_documents', 0);
    }

    public function test_unapproved_source_and_missing_generation_font_are_setup_errors_not_detailed_fallbacks(): void
    {
        $payment = $this->payment();
        $this->login($this->admin, 'admin');
        $this->get(route('payments.receipt.download', $payment))->assertStatus(409)->assertSee('review and confirm the original Resibo Butaw source');
        $this->readySource();
        config(['certificates.butaw_font' => 'missing.ttf']);
        $this->get(route('payments.receipt.download', $payment))->assertStatus(409)->assertSee('Install the licensed Calibri font');
        config(['certificates.butaw_source_directory' => 'missing-source']);
        $this->get(route('payments.receipt.download', $payment))->assertStatus(409)->assertSee('original Resibo Butaw PDF');
        $this->assertDatabaseCount('payment_documents', 0);
    }

    public function test_unsupported_payments_keep_clearly_labelled_detailed_receipts(): void
    {
        $this->login($this->admin, 'admin');
        foreach (['electronic', 'penalty', 'year', 'provenance'] as $case) {
            $payment = $this->payment();
            if ($case === 'electronic') {
                $payment->update(['payment_method' => 'GCash']); // Historical fixture, not a new electronic submission.
            } elseif ($case === 'penalty') {
                $payment->update(['penalty' => '1.00']);
            } elseif ($case === 'year') {
                $payment->duesObligation->update(['billing_year' => 2025]);
            } else {
                $payment->update(['property_snapshot' => null]);
            }
            $this->assertSame('detailed', app(PaymentReceiptResolver::class)->state($payment->fresh()));
            $this->get(route('payments.receipt.download', $payment))->assertOk()->assertHeader('content-type', 'application/pdf')
                ->assertDownload('hoa-receipt-'.$payment->or_number.'.pdf');
        }
        $this->assertDatabaseCount('payment_documents', 0);
    }

    public function test_managers_liable_owner_and_actual_tenant_payer_can_read_but_unrelated_household_members_cannot(): void
    {
        $payment = $this->payment();
        $tenant = $this->member($payment->homeowner, 'Tenant');
        $coowner = $this->member($payment->homeowner, 'Co-owner');
        $payment->update(['payer_homeowner_id' => $tenant->id]);
        $document = $this->document($payment);
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        foreach ([$this->admin, $staff, $payment->homeowner->user, $tenant->user] as $actor) {
            $this->login($actor);
            $this->get(route('payments.receipt.download', $payment))->assertOk();
            $this->get(route('certificates.butaw.download', $document))->assertOk();
        }
        $this->login($coowner->user);
        $this->get(route('payments.receipt.download', $payment))->assertNotFound();
        $this->get(route('certificates.butaw.download', $document))->assertNotFound();
        $this->login($tenant->user);
        $this->get(route('certificates.butaw.preview', Str::random(64)))->assertForbidden();
        $this->get(route('certificates.butaw.reference'))->assertForbidden();
    }

    public function test_missing_or_corrupted_saved_files_are_readable_errors_without_regeneration(): void
    {
        $this->login($this->admin, 'admin');
        foreach (['missing', 'changed'] as $case) {
            $payment = $this->payment();
            $document = $this->document($payment);
            if ($case === 'missing') {
                Storage::disk('local')->delete($document->path);
            } else {
                Storage::disk('local')->put($document->path, 'Corrupted synthetic file');
            }
            foreach ([route('payments.receipt.download', $payment), route('certificates.butaw.download', $document)] as $url) {
                $this->get($url)->assertStatus($case === 'missing' ? 404 : 409)->assertSee('The saved receipt is missing, changed, or unavailable.');
            }
        }
        $this->assertDatabaseCount('payment_documents', 2);
        $this->assertDatabaseCount('payment_document_previews', 0);
    }

    public function test_rejected_deleted_and_inactive_access_remains_denied(): void
    {
        $payment = $this->payment();
        $document = $this->document($payment);
        $this->login($this->admin, 'admin');
        foreach (['Pending', 'Rejected'] as $status) {
            $payment->update(['review_status' => $status]);
            $this->get(route('payments.receipt.download', $payment))->assertNotFound();
            $this->get(route('certificates.butaw.download', $document))->assertNotFound();
        }
        $payment->update(['review_status' => 'Recorded']);
        $payment->delete();
        $this->get(route('payments.receipt.download', $payment))->assertNotFound();
        $this->get(route('certificates.butaw.download', $document))->assertNotFound();
        $this->assertSame('0.00', $payment->duesObligation->fresh()->amount_paid);
        $this->admin->forceFill(['account_status' => 'Inactive'])->save();
        $this->login($this->admin->fresh(), 'admin');
        $this->get(route('certificates.butaw.download', $document))->assertForbidden();
    }

    public function test_table_actions_switch_from_generation_to_shared_download_and_label_detailed_receipts(): void
    {
        $payment = $this->payment();
        $this->login($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(ListPayments::class)->assertTableActionVisible('generateMonthlyReceipt', $payment)->assertTableActionHidden('receipt', $payment);
        $document = $this->document($payment);
        Livewire::test(ListPayments::class)->assertTableActionVisible('receipt', $payment)->assertTableActionVisible('printReceipt', $payment)
            ->assertTableActionHidden('generateMonthlyReceipt', $payment)
            ->assertTableActionHasUrl('receipt', route('certificates.butaw.download', $document), $payment);
        $payment->update(['property_snapshot' => null]);
        Livewire::test(ListPayments::class)->assertTableActionVisible('detailedReceipt', $payment)->assertTableActionHidden('receipt', $payment);
    }

    public function test_eager_loaded_receipt_availability_has_no_per_row_database_queries(): void
    {
        $this->document($this->payment());
        $this->payment();
        $resolver = app(PaymentReceiptResolver::class);
        $payments = $resolver->withReceipts(Payment::query())->get();
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ($payments as $payment) {
            $resolver->state($payment);
            $resolver->documentUrl($payment);
        }
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_missing_schema_is_setup_guidance_not_an_sql_exception_or_alternate_pdf(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Transactional missing-schema DDL is intentionally SQLite-only.');
        }
        $payment = $this->payment();
        Schema::drop('payment_document_previews');
        $this->login($this->admin, 'admin');
        $this->get(route('payments.receipt.download', $payment))->assertStatus(409)->assertSee(ButawReceiptSource::SETUP_MESSAGE);
    }

    public function test_certificate_only_staff_cannot_use_the_payment_generation_shortcut(): void
    {
        $payment = $this->payment();
        Role::findByName('hoa_staff', 'web')->revokePermissionTo('manage_payments');
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $this->login($staff, 'staff');
        $this->get('/staff/certificates/create?document=butaw&payment='.$payment->id)->assertForbidden();
    }

    public function test_saved_receipts_remain_readable_when_temporary_preview_schema_is_unavailable(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Transactional missing-schema DDL is intentionally SQLite-only.');
        }
        $payment = $this->payment();
        $document = $this->document($payment);
        Schema::drop('payment_document_previews');
        $this->login($this->admin, 'admin');
        foreach ([route('payments.receipt.download', $payment), route('certificates.butaw.download', $document)] as $url) {
            $this->assertSame(Storage::disk('local')->get($document->path), $this->get($url)->assertOk()->streamedContent());
        }
    }

    public function test_receipt_generation_uses_the_form_confirmation_before_persisting(): void
    {
        if (! is_file((string) config('certificates.butaw_font'))) {
            $this->markTestSkipped('Original receipt generation requires the licensed Calibri font.');
        }
        $payment = $this->payment();
        $this->readySource();
        $this->login($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        $action = TestAction::make('generateReceipt')->schemaComponent('form-actions', 'content');
        $page = Livewire::withQueryParams(['document' => 'butaw', 'payment' => $payment->id])->test(CreateCertificate::class)
            ->call('mountAction', 'previewResidency', [], ['schemaComponent' => 'content.form-actions'])
            ->assertHasNoErrors()->assertSet('previewToken', fn ($token) => filled($token))
            ->mountAction($action)->assertActionMounted($action);
        $this->assertDatabaseCount('payment_documents', 0);
        $page->callMountedAction()->assertHasNoErrors()->assertSet('receiptDocumentId', fn ($id) => $id !== null);
        $this->assertDatabaseCount('payment_documents', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('100.00', $payment->duesObligation->fresh()->amount_paid);
    }

    private function login(User $user, string $guard = 'web'): void
    {
        foreach (['admin', 'staff', 'homeowner', 'web'] as $name) {
            Auth::guard($name)->logout();
        }
        Filament::setCurrentPanel(null);
        $this->actingAs($user, $guard);
    }

    private function payment(): Payment
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');
        $resident = Homeowner::create(['user_id' => $user->id, 'street' => 'Test Street', 'block' => (string) $user->id,
            'lot' => '2', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
        $schedule = DuesSetting::create(['name' => 'Shared receipts '.Str::random(8), 'amount' => '500.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);
        $due = DuesObligation::create(['homeowner_id' => $resident->id, 'dues_setting_id' => $schedule->id, 'billing_year' => 2026, 'billing_month' => 10,
            'due_date' => '2026-10-31', 'amount_due' => '500.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending']);

        return app(RecordPayment::class)->handle(['dues_obligation_id' => $due->id, 'amount_paid' => '100.00', 'payment_method' => 'Cash', 'payment_date' => '2026-10-20'], $this->admin);
    }

    private function member(Homeowner $owner, string $type): Homeowner
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');

        return Homeowner::create([...$owner->only(['street', 'block', 'lot', 'phase', 'phase_suffix', 'residency_date']),
            'user_id' => $user->id, 'ownership_type' => $type, 'status' => 'Active', 'landlord_homeowner_id' => $type === 'Tenant' ? $owner->id : null]);
    }

    /** Synthetic saved-file fixture, never an official document or source approval. */
    private function document(Payment $payment, mixed $createdAt = null): PaymentDocument
    {
        $bytes = '%PDF-1.4 SYNTHETIC SAVED RECEIPT '.Str::uuid();
        $source = PaymentDocumentSource::create(['source_checksum' => hash('sha256', $bytes), 'manifest' => ['font_profile' => 'butaw'],
            'approved_by' => $this->admin->id, 'approved_at' => now(), 'approval_note' => 'Synthetic test fixture only.']);
        $path = 'payment-documents/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $bytes);

        return PaymentDocument::create(['payment_id' => $payment->id, 'payment_document_source_id' => $source->id, 'source_checksum' => $source->source_checksum,
            'snapshot' => app(ButawReceiptEligibility::class)->snapshot($payment), 'path' => $path, 'file_checksum' => hash('sha256', $bytes),
            'generated_by' => $this->admin->id, 'created_at' => $createdAt ?? now()]);
    }

    private function readySource(): void
    {
        $manifest = app(ButawReceiptSource::class)->manifest();
        PaymentDocumentSource::create(['source_checksum' => $manifest['sha256'], 'manifest' => $manifest,
            'approved_by' => $this->admin->id, 'approved_at' => now(), 'approval_note' => 'Synthetic test fixture only, not an HOA approval.']);
        Storage::disk('local')->put($manifest['background_path'], app(ButawReceiptSource::class)->originalPdf());
    }
}
