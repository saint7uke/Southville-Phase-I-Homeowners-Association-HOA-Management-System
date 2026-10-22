<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\ConfirmReceiptDetails;
use App\Actions\Payments\RecordPayment;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Models\Certificate;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\PaymentDocument;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ButawReceiptEligibility;
use App\Services\ButawReceipts;
use App\Services\ButawReceiptSource;
use App\Services\OriginalCertificateRenderer;
use App\Support\OriginalLayoutPdf;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ButawReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 20));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::set('local', Storage::fake('butaw-tests-'.getmypid()));
        $this->admin = User::factory()->create(['account_status' => 'Active', 'first_name' => 'José', 'middle_name' => null, 'last_name' => 'Dela Cruz']);
        $this->admin->assignRole('hoa_admin');
    }

    public function test_recorded_partial_cash_payment_has_property_and_collector_snapshot(): void
    {
        $payment = $this->payment();
        $snapshot = app(ButawReceiptEligibility::class)->snapshot($payment);
        $this->assertSame('100.00', $snapshot['values']['amount']);
        $this->assertSame('October', $snapshot['values']['month']);
        $this->assertSame($this->admin->full_name, $snapshot['values']['collector']);
        $this->assertSame('Partial', $payment->status);
        $this->assertSame('A', $payment->property_snapshot['phase_suffix']);
    }

    public function test_receipt_source_confirmation_is_hidden_once_the_current_source_is_approved(): void
    {
        $this->assertFalse(app(ButawReceiptSource::class)->isConfirmed());
        $this->syntheticSource();
        $this->assertTrue(app(ButawReceiptSource::class)->isConfirmed());
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(ListCertificates::class)->assertActionHidden('confirmButawSource')
            ->assertActionVisible('reviewButawSource')->assertActionVisible('findPaymentReceipt');
        $this->assertDatabaseCount('payment_document_sources', 1);
    }

    public function test_eligibility_rejects_unsupported_payments_and_missing_provenance(): void
    {
        foreach ([['payment_method' => 'GCash'], ['review_status' => 'Pending'], ['review_status' => 'Rejected'],
            ['penalty' => '0.01'], ['property_snapshot' => null], ['property_snapshot' => ['block' => '1', 'lot' => '2', 'collector_name' => 'Unverified']]] as $change) {
            $payment = $this->payment();
            $payment->forceFill($change)->save();
            try {
                app(ButawReceiptEligibility::class)->snapshot($payment->fresh());
                $this->fail('Unsupported payment should not receive an original receipt.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment_id', $exception->errors());
            }
        }
        $payment = $this->payment();
        $payment->duesObligation->update(['billing_year' => 2027]);
        $this->expectException(ValidationException::class);
        app(ButawReceiptEligibility::class)->snapshot($payment->fresh());
    }

    public function test_missing_uploaded_pdf_is_a_readable_setup_error_not_a_fallback(): void
    {
        config(['certificates.butaw_source_directory' => Storage::disk('local')->path('missing-source')]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('original Resibo Butaw PDF or reviewed blank-field mapping is missing');
        app(ButawReceiptSource::class)->manifest();
    }

    public function test_real_uploaded_source_can_render_with_unchanged_checksum_and_no_extra_printed_identifiers(): void
    {
        if (! is_file((string) config('certificates.butaw_font'))) {
            $this->markTestSkipped('The exact licensed Calibri font is unavailable.');
        }
        $source = app(ButawReceiptSource::class);
        $manifest = $source->manifest();
        $this->assertSame(1, $manifest['visible_slips']);
        $this->assertSame('pdf', $manifest['source_format']);
        $this->assertSame('ae0f3fb8ca5470aeebb97c22eec429536e0e71a7648ef0cf8f91cc389361dc30', $manifest['sha256']);
        $this->assertSame(['amount', 'month', 'block', 'lot', 'collector'], array_column($manifest['fields'], 'key'));
        $this->assertSame([11.04], array_values(array_unique(array_column($manifest['fields'], 'font_size'))));
        $source->approve(['reviewed' => true, 'note' => 'Synthetic engineering approval in a disposable test only.'], $this->admin);
        $payment = $this->payment();
        $service = app(ButawReceipts::class);
        $document = $service->generate($service->prepare($payment->id, $this->admin), $payment->id, $this->admin);
        $this->assertSame($manifest['sha256'], $document->source_checksum);
        $this->assertSame($manifest['source_sha256'], $document->snapshot['layout']['source_sha256']);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($document->path));
    }

    public function test_source_review_returns_exact_uploaded_bytes_without_creating_approval_or_requiring_a_font(): void
    {
        config(['certificates.butaw_font' => 'unavailable-font.ttf']);
        $bytes = file_get_contents(resource_path('certificates/resibo-butaw-pdf-2026/reference.pdf'));
        $response = $this->actingAs($this->admin, 'admin')->get(route('certificates.butaw.reference'))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($bytes, $response->getContent());
        $this->assertDatabaseCount('payment_document_sources', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('certificate-backgrounds'));
    }

    public function test_staff_cannot_review_the_original_source(): void
    {
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $this->actingAs($staff, 'staff')->get(route('certificates.butaw.reference'))->assertForbidden();
    }

    public function test_old_receipts_remain_downloadable_but_approval_does_not_transfer_to_the_new_pdf(): void
    {
        if (! is_file((string) config('certificates.butaw_font'))) {
            $this->markTestSkipped('The exact licensed Calibri font is unavailable.');
        }
        $directory = config('certificates.butaw_source_directory');
        config(['certificates.butaw_source_directory' => resource_path('certificates/resibo-butaw-2026')]);
        $source = app(ButawReceiptSource::class);
        $oldSource = $source->approve(['reviewed' => true, 'note' => 'Synthetic legacy source approval in an isolated test.'], $this->admin);
        $payment = $this->payment();
        $service = app(ButawReceipts::class);
        $oldPreview = $service->prepare($payment->id, $this->admin);
        $document = $service->generate($oldPreview, $payment->id, $this->admin);
        $oldBytes = Storage::disk('local')->get($document->path);
        config(['certificates.butaw_source_directory' => $directory]);
        $this->assertFalse($source->isConfirmed());
        // Native MySQL canonicalizes JSON object-key order; compare all cast values, not serialized key order.
        $this->assertEquals($oldSource->attributesToArray(), $oldSource->fresh()->attributesToArray());
        $this->actingAs($this->admin, 'admin')->get(route('certificates.butaw.download', $document))->assertOk();
        $this->assertSame($oldBytes, Storage::disk('local')->get($document->path));
        $source->approve(['reviewed' => true, 'note' => 'Synthetic approval of the newly uploaded single-slip PDF.'], $this->admin);
        $this->assertDatabaseCount('payment_document_sources', 2);
        $this->expectException(ValidationException::class);
        $service->read($oldPreview, $this->admin);
    }

    public function test_uploaded_pdf_rejects_overflow_and_unsupported_text_without_creating_a_receipt(): void
    {
        if (! is_file((string) config('certificates.butaw_font'))) {
            $this->markTestSkipped('The exact licensed Calibri font is unavailable.');
        }
        $source = app(ButawReceiptSource::class);
        $source->approve(['reviewed' => true, 'note' => 'Synthetic original-PDF approval for isolated validation tests.'], $this->admin);
        foreach ([str_repeat('W', 100), "Collector\nAnother line", "Collector \u{10FFFF}"] as $value) {
            try {
                app(OriginalCertificateRenderer::class)->render($source->manifest(), ['collector' => $value]);
                $this->fail('Do not shrink, clip or substitute text to fit the original PDF.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment_id', $exception->errors());
            }
        }
        $this->assertDatabaseCount('payment_documents', 0);
    }

    public function test_receipt_history_has_an_empty_state_and_populates_only_after_generation(): void
    {
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        $page = Livewire::test(ListCertificates::class)->mountAction('findPaymentReceipt')
            ->assertSchemaComponentVisible('receipt_history_empty')
            ->assertSchemaComponentExists('receipt_history_empty', null, fn ($component) => $component->getLabel() === 'No generated receipts yet' && str_contains((string) $component->getState(), 'document=butaw'))
            ->assertSchemaComponentExists('receipt_history_description', null, fn ($component) => str_contains((string) $component->getState(), 'Recording a payment does not automatically create a Resibo Butaw PDF.'));
        $page->assertFormFieldHidden('document_id', $page->instance()->getMountedActionSchemaName());
        $this->syntheticSource();
        $payment = $this->payment();
        $this->assertDatabaseCount('payment_documents', 0);
        $service = app(ButawReceipts::class);
        $document = $service->generate($service->prepare($payment->id, $this->admin), $payment->id, $this->admin);
        $page = Livewire::test(ListCertificates::class)->mountAction('findPaymentReceipt');
        $page->assertFormFieldVisible('document_id', $page->instance()->getMountedActionSchemaName())
            ->fillForm(['document_id' => $document->id], $page->instance()->getMountedActionSchemaName())
            ->assertSchemaComponentExists('document_links', null, fn ($component) => str_contains((string) $component->getState(), 'View / Print') && str_contains((string) $component->getState(), 'Download'));
    }

    public function test_receipt_history_shows_setup_guidance_when_the_schema_is_incomplete(): void
    {
        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('Transactional missing-table simulation is SQLite-only; do not commit DDL inside a MySQL fixture transaction.');
        }
        Schema::drop('payment_document_previews');
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(ListCertificates::class)->mountAction('findPaymentReceipt')
            ->assertSchemaComponentExists('receipt_history_empty', null, fn ($component) => $component->getState() === ButawReceiptSource::SETUP_MESSAGE);
    }

    public function test_receipt_shortcut_preselects_payment_mode_but_does_not_override_a_service_request(): void
    {
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)->assertSet('data.document_kind', 'butaw');
        $payment = $this->payment();
        $request = ServiceRequest::create(['homeowner_id' => $payment->homeowner_id, 'request_type' => 'Certificate of Residency', 'subject' => 'Synthetic certificate request', 'details' => 'Registered residency', 'status' => 'Approved']);
        Livewire::withQueryParams(['document' => 'butaw', 'request' => $request->id])->test(CreateCertificate::class)
            ->assertSet('sourceRequestId', $request->id)->assertSet('data.type', 'Certificate of Residency');
    }

    public function test_receipt_shortcut_cannot_bypass_payment_permissions(): void
    {
        Role::findByName('hoa_admin', 'web')->revokePermissionTo('manage_payments');
        $this->actingAs($this->admin->fresh(), 'admin')->get('/admin/certificates/create?document=butaw')->assertForbidden();
    }

    public function test_legacy_receipt_confirmation_is_explicit_audited_and_does_not_change_financial_fields(): void
    {
        $payment = $this->payment();
        $payment->update(['property_snapshot' => null]);
        $ledger = $payment->duesObligation->getAttributes();
        $confirmed = app(ConfirmReceiptDetails::class)->handle($payment, ['confirmed' => true, 'note' => 'Synthetic evidence confirms this property and recorded collector.'], $this->admin);
        $this->assertSame($this->admin->id, $confirmed->property_snapshot['confirmed_by']);
        $this->assertSame($ledger, $payment->duesObligation->fresh()->getAttributes());
        $this->assertSame('100.00', $confirmed->amount_paid);
        $this->expectException(ValidationException::class);
        app(ConfirmReceiptDetails::class)->handle($confirmed, ['confirmed' => true, 'note' => 'Cannot replace an existing verified snapshot.'], $this->admin);
    }

    public function test_generation_failure_rolls_back_the_document_and_removes_its_pdf(): void
    {
        $this->syntheticSource();
        $payment = $this->payment();
        $token = app(ButawReceipts::class)->prepare($payment->id, $this->admin);
        Event::listen('eloquent.creating: '.PaymentDocument::class, fn () => throw new \RuntimeException('Synthetic persistence failure'));
        try {
            app(ButawReceipts::class)->generate($token, $payment->id, $this->admin);
            $this->fail('Document creation must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic persistence failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.PaymentDocument::class);
        }
        $this->assertDatabaseCount('payment_documents', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles('payment-documents'));
        $this->assertSame('100.00', $payment->duesObligation->fresh()->amount_paid);
    }

    public function test_preview_generation_is_private_idempotent_and_does_not_change_the_ledger(): void
    {
        $this->syntheticSource();
        $payment = $this->payment();
        $ledger = $payment->duesObligation->getAttributes();
        $service = app(ButawReceipts::class);
        $first = $service->prepare($payment->id, $this->admin);
        $second = $service->prepare($payment->id, $this->admin);
        $this->assertDatabaseCount('payment_documents', 0);
        $document = $service->generate($first, $payment->id, $this->admin);
        $this->assertSame($document->id, $service->generate($first, $payment->id, $this->admin)->id);
        $this->assertSame($document->id, $service->generate($second, $payment->id, $this->admin)->id);
        $this->assertDatabaseCount('payment_documents', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($ledger, $payment->duesObligation->fresh()->getAttributes());
        $this->assertSame(0, Certificate::count());
        $bytes = Storage::disk('local')->get($document->path);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringNotContainsString('/AcroForm', $bytes);
        $this->actingAs($this->admin, 'admin')->get(route('certificates.butaw.download', $document))->assertOk()->assertDownload();
        $this->get(route('certificates.butaw.download', ['paymentDocument' => $document, 'view' => 1]))->assertOk()->assertHeader('Content-Disposition', 'inline; filename=resibo-butaw-'.$document->id.'.pdf');
        $payment->update(['review_status' => 'Rejected']);
        $this->get(route('certificates.butaw.download', $document))->assertNotFound();
    }

    public function test_preview_expiry_cross_user_and_certificate_only_permission_are_enforced(): void
    {
        $this->syntheticSource();
        $payment = $this->payment();
        $service = app(ButawReceipts::class);
        $token = $service->prepare($payment->id, $this->admin);
        $other = User::factory()->create(['account_status' => 'Active']);
        $other->assignRole('hoa_admin');
        try {
            $service->read($token, $other);
            $this->fail('Preview must belong to its actor.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preview_token', $exception->errors());
        }
        $this->travel(16)->minutes();
        try {
            $service->generate($token, $payment->id, $this->admin);
            $this->fail('Expired previews must not generate documents.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preview_token', $exception->errors());
        }
        $certificateOnly = User::factory()->create(['account_status' => 'Active']);
        $certificateOnly->givePermissionTo('manage_requests');
        $this->expectException(AuthorizationException::class);
        $service->prepare($payment->id, $certificateOnly);
    }

    public function test_integrated_create_page_generates_a_payment_document_not_a_certificate(): void
    {
        $this->syntheticSource();
        $payment = $this->payment();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($this->admin, 'admin')->test(CreateCertificate::class)
            ->fillForm(['document_kind' => 'butaw', 'receipt_homeowner_id' => $payment->homeowner_id, 'payment_id' => $payment->id])->call('preparePreview')->assertHasNoFormErrors()
            ->call('generateReceipt')->assertHasNoFormErrors()->assertNotified('Monthly dues receipt is ready.');
        $this->assertSame(1, PaymentDocument::count());
        $this->assertSame(0, Certificate::count());
    }

    public function test_receipt_form_requires_the_liable_homeowner_and_shows_no_payment_options_until_selected(): void
    {
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)
            ->assertFormFieldDisabled('payment_id')->assertSchemaComponentExists('payment_id', null, fn ($field) => $field->getOptions() === [])
            ->call('preparePreview')->assertHasFormErrors(['receipt_homeowner_id' => 'required']);
        $this->assertDatabaseCount('payment_document_previews', 0);
    }

    public function test_receipt_form_filters_payments_by_liable_homeowner_and_uses_snapshot_values(): void
    {
        $payment = $this->payment();
        $other = $this->payment();
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        $page = Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)
            ->set('data.receipt_homeowner_id', $payment->homeowner_id)
            ->assertSchemaComponentExists('payment_id', null, fn ($field) => array_keys($field->getOptions()) === [$payment->id])
            ->set('data.payment_id', $payment->id)
            ->assertSee('Automatically filled receipt details')->assertSee($payment->or_number)
            ->assertSee('October 2026')->assertSee('PHP 100.00')->assertSee($this->admin->full_name)
            ->assertSee('Only the amount, month, block, lot, and recorded collector are printed');
        $details = $page->instance()->receiptDetails();
        $this->assertSame(app(ButawReceiptEligibility::class)->snapshot($payment)['values'], $details['values']);
        $page->set('data.payment_id', $other->id);
        $this->assertNull($page->instance()->receiptDetails());
    }

    public function test_archived_liable_accounts_are_selectable_only_when_they_have_an_eligible_payment(): void
    {
        $payment = $this->payment();
        $unsupported = $this->payment();
        $unsupported->update(['review_status' => 'Rejected']);
        foreach ([$payment, $unsupported] as $item) {
            $item->homeowner->delete();
            $item->homeowner->user->delete();
        }
        $this->assertTrue(app(ButawReceiptEligibility::class)->homeowners()->whereKey($payment->homeowner_id)->exists());
        $this->assertFalse(app(ButawReceiptEligibility::class)->homeowners()->whereKey($unsupported->homeowner_id)->exists());
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)
            ->set('data.receipt_homeowner_id', $payment->homeowner_id)
            ->assertSchemaComponentExists('receipt_homeowner_id', null, fn ($field) => str_contains($field->getOptionLabel(), 'Archived'))
            ->set('data.payment_id', $payment->id)->assertSee($payment->or_number);
    }

    public function test_homeowner_changes_clear_the_payment_details_preview_and_generated_links(): void
    {
        $this->syntheticSource();
        $payment = $this->payment();
        $other = $this->payment();
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        $page = Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)
            ->set('data.receipt_homeowner_id', $payment->homeowner_id)->set('data.payment_id', $payment->id)
            ->call('preparePreview')->assertHasNoFormErrors();
        $this->assertNotNull($page->get('previewToken'));
        $page->set('data.payment_id', null)->assertSet('previewToken', null);
        $page->set('data.payment_id', $payment->id)->call('preparePreview')->call('generateReceipt')->assertHasNoFormErrors();
        $this->assertNotNull($page->get('receiptDocumentId'));
        $page->set('data.receipt_homeowner_id', $other->homeowner_id)->assertSet('data.payment_id', null)
            ->assertSet('previewToken', null)->assertSet('receiptDocumentId', null);
        $this->assertNull($page->instance()->receiptDetails());
        $page->set('data.document_kind', 'certificate')->assertSet('data.receipt_homeowner_id', null);
    }

    public function test_mismatched_homeowner_context_is_rejected_before_preview_and_locked_generation(): void
    {
        $this->syntheticSource();
        $payment = $this->payment();
        $other = $this->payment();
        $service = app(ButawReceipts::class);
        foreach (['prepare', 'generate'] as $step) {
            try {
                if ($step === 'prepare') {
                    $service->prepare($payment->id, $this->admin, $other->homeowner_id);
                } else {
                    $token = $service->prepare($payment->id, $this->admin, $payment->homeowner_id);
                    $service->generate($token, $payment->id, $this->admin, $other->homeowner_id);
                }
                $this->fail('A receipt cannot use a different liable homeowner.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment_id', $exception->errors());
            }
        }
        $this->assertDatabaseCount('payment_documents', 0);
        $this->assertDatabaseCount('payment_document_previews', 1);
    }

    public function test_no_payment_guidance_and_filipino_copy_preserve_form_values(): void
    {
        $payment = $this->payment();
        $payment->update(['review_status' => 'Rejected']);
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)
            ->set('data.receipt_homeowner_id', $payment->homeowner_id)
            ->assertSee('This homeowner has no eligible recorded payment')->assertSee('Record Cash payment')
            ->call('preparePreview')->assertHasFormErrors(['payment_id' => 'required'])
            ->assertSet('data.receipt_homeowner_id', $payment->homeowner_id);
        app()->setLocale('fil');
        Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)
            ->assertSee('May-ari / account na may pananagutan sa ari-arian')->assertSee('Piliin muna');
    }

    public function test_payment_management_staff_without_certificate_permissions_can_use_receipt_fields(): void
    {
        $payment = $this->payment();
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        Role::findByName('hoa_staff', 'web')->revokePermissionTo('manage_requests');
        $this->actingAs($staff->fresh(), 'staff');
        Filament::setCurrentPanel('staff');
        Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)
            ->assertFormFieldHidden('homeowner_id')->assertFormFieldVisible('receipt_homeowner_id')
            ->set('data.receipt_homeowner_id', $payment->homeowner_id)->set('data.payment_id', $payment->id)
            ->assertSee('Automatically filled receipt details')->assertSee($payment->or_number);
    }

    public function test_changed_payment_is_rechecked_and_forged_readonly_values_are_never_printed(): void
    {
        $this->syntheticSource();
        $payment = $this->payment();
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        $page = Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)
            ->set('data.receipt_homeowner_id', $payment->homeowner_id)->set('data.payment_id', $payment->id)
            ->set('data.receipt_details', ['amount' => '99999.00', 'collector' => 'Forged collector'])
            ->call('preparePreview')->assertHasNoFormErrors();
        $token = $page->get('previewToken');
        $this->assertSame('100.00', app(ButawReceipts::class)->read($token, $this->admin)->snapshot['values']['amount']);
        $payment->update(['review_status' => 'Rejected']);
        $page->call('generateReceipt')->assertHasFormErrors(['payment_id']);
        $this->assertDatabaseCount('payment_documents', 0);
    }

    public function test_actual_tenant_payer_is_distinct_from_the_liable_homeowner_in_receipt_details(): void
    {
        $existing = $this->payment();
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');
        $tenant = Homeowner::create([
            ...$existing->homeowner->only(['street', 'block', 'lot', 'phase', 'phase_suffix', 'residency_date']),
            'user_id' => $user->id, 'ownership_type' => 'Tenant', 'status' => 'Active', 'landlord_homeowner_id' => $existing->homeowner_id,
        ]);
        $payment = app(RecordPayment::class)->handle(['dues_obligation_id' => $existing->dues_obligation_id, 'payer_homeowner_id' => $tenant->id,
            'payment_method' => 'Cash', 'amount_paid' => '25.00', 'payment_date' => '2026-10-20'], $this->admin);
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        $page = Livewire::withQueryParams(['document' => 'butaw'])->test(CreateCertificate::class)
            ->set('data.receipt_homeowner_id', $existing->homeowner_id)->set('data.payment_id', $payment->id);
        $this->assertSame($user->full_name, $page->instance()->receiptDetails()['payer_name']);
        $this->assertSame($existing->homeowner->user->full_name, $page->instance()->receiptDetails()['liable_name']);
        $this->assertSame('25.00', $page->instance()->receiptDetails()['values']['amount']);
        $page->set('data.receipt_homeowner_id', $tenant->id)->assertSchemaComponentExists('payment_id', null, fn ($field) => $field->getOptions() === []);
    }

    public function test_forged_receipt_actions_cannot_be_called_with_only_certificate_permissions(): void
    {
        $payment = $this->payment();
        Role::findByName('hoa_admin', 'web')->revokePermissionTo('manage_payments');
        $this->actingAs($this->admin->fresh(), 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(CreateCertificate::class)->set('data.document_kind', 'butaw')
            ->set('data.receipt_homeowner_id', $payment->homeowner_id)->set('data.payment_id', $payment->id)
            ->call('preparePreview')->assertForbidden();
        Livewire::test(CreateCertificate::class)->call('generateReceipt')->assertForbidden();
        $this->assertDatabaseCount('payment_document_previews', 0);
        $this->assertDatabaseCount('payment_documents', 0);
    }

    public function test_payment_date_changes_invalidate_the_reviewed_details_before_generation(): void
    {
        $this->syntheticSource();
        $payment = $this->payment();
        $service = app(ButawReceipts::class);
        $token = $service->prepare($payment->id, $this->admin, $payment->homeowner_id);
        $payment->update(['payment_date' => '2026-10-19']);
        try {
            $service->generate($token, $payment->id, $this->admin, $payment->homeowner_id);
            $this->fail('Changed details require a fresh preview.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preview_token', $exception->errors());
        }
        $this->assertDatabaseCount('payment_documents', 0);
    }

    private function payment(): Payment
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');
        $resident = Homeowner::create(['user_id' => $user->id, 'house_number' => null, 'street' => 'Test Street',
            'block' => (string) $user->id, 'lot' => '2', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
        $schedule = DuesSetting::create(['name' => 'Test monthly '.Str::random(8), 'amount' => '500.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);
        $obligation = DuesObligation::create(['homeowner_id' => $resident->id, 'dues_setting_id' => $schedule->id, 'billing_year' => 2026, 'billing_month' => 10,
            'due_date' => '2026-10-31', 'amount_due' => '500.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending']);

        return app(RecordPayment::class)->handle(['dues_obligation_id' => $obligation->id, 'payment_method' => 'Cash', 'amount_paid' => '100.00', 'penalty' => '0.00', 'payment_date' => '2026-10-20'], $this->admin);
    }

    /** Synthetic engineering fixture only: never the official uploaded design or a production source. */
    private function syntheticSource(): void
    {
        if (! is_file((string) config('certificates.butaw_font'))) {
            $this->markTestSkipped('The exact licensed Calibri font is unavailable.');
        }
        $disk = Storage::disk('local');
        $directory = $disk->path('SYNTHETIC-SOURCE-TEST-ONLY');
        $disk->makeDirectory('SYNTHETIC-SOURCE-TEST-ONLY');
        $pdf = new OriginalLayoutPdf;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage('P', [612, 792]);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Text(30, 30, 'SYNTHETIC TEST ONLY - NOT AN OFFICIAL SOURCE');
        $bytes = $pdf->Output('', 'S');
        $disk->put('SYNTHETIC-SOURCE-TEST-ONLY/original.docx', 'SYNTHETIC TEST INPUT');
        $disk->put('SYNTHETIC-SOURCE-TEST-ONLY/reference.pdf', $bytes);
        $fields = [];
        foreach (['amount', 'month', 'block', 'lot', 'collector'] as $index => $key) {
            $fields[] = ['key' => $key, 'page' => 1, 'x' => 30, 'y' => 60 + $index * 20, 'width' => 400, 'height' => 14, 'font_size' => 11];
        }
        $disk->put('SYNTHETIC-SOURCE-TEST-ONLY/layout.json', json_encode(['source_sha256' => hash('sha256', 'SYNTHETIC TEST INPUT'), 'sha256' => hash('sha256', $bytes),
            'printed_year' => 2026, 'font_profile' => 'butaw', 'font_sha256' => hash_file('sha256', config('certificates.butaw_font')), 'pages' => [['width' => 612, 'height' => 792]], 'fields' => $fields], JSON_THROW_ON_ERROR));
        config(['certificates.butaw_source_directory' => $directory]);
        app(ButawReceiptSource::class)->approve(['reviewed' => true, 'note' => 'Synthetic approval for automated tests only.'], $this->admin);
    }
}
