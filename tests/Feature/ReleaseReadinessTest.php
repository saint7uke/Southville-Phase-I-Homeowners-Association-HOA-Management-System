<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Reports;
use App\Models\User;
use App\Notifications\WorkflowStatusChanged;
use App\Support\NativeMysqlAcceptance;
use App\Validation\CaseAttachmentRules;
use App\Validation\ComplaintSubmissionValidator;
use App\Validation\HomeownerProfileValidator;
use App\Validation\ServiceRequestSubmissionValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class ReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dictionaries_have_identical_keys_and_preserve_placeholders(): void
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
        $filipino = json_decode(file_get_contents(lang_path('fil.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEqualsCanonicalizing(array_keys($english), array_keys($filipino));
        foreach ($english as $key => $value) {
            $this->assertNotEmpty(trim($filipino[$key]), $key);
            preg_match_all('/:[a-zA-Z_]+|\{\{\w+\}\}/', $value, $expected);
            preg_match_all('/:[a-zA-Z_]+|\{\{\w+\}\}/', $filipino[$key], $actual);
            $this->assertEqualsCanonicalizing($expected[0], $actual[0], $key);
        }
    }

    public function test_filipino_profile_validation_and_availability_use_shared_copy(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['preferred_locale' => 'fil']);
        $user->assignRole('homeowner');
        $homeowner = $user->homeowner()->create(['house_number' => '9', 'street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
        $this->actingAs($user, 'homeowner')->postJson(route('homeowner.profile.availability'), ['field' => 'email', 'email' => $user->email])
            ->assertOk()->assertExactJson(['valid' => true, 'message' => 'Maaaring gamitin ang email address na ito.']);
        app()->setLocale('fil');
        $this->assertSame('Maglagay ng 11-digit na mobile number sa Pilipinas na nagsisimula sa 09.', app(HomeownerProfileValidator::class)->messages()['contact_number.regex']);
        $this->assertSame('Active', $homeowner->fresh()->status);
    }

    public function test_notification_translates_status_but_preserves_names_remarks_and_reference(): void
    {
        app()->setLocale('fil');
        $user = User::factory()->make(['first_name' => 'Pending', 'middle_name' => null, 'last_name' => 'English Content', 'suffix' => null]);
        $notification = new WorkflowStatusChanged('Complaints', 'CMP-2026-42', 'Resolved', 'Keep this English resident remark.');
        $mail = $notification->toMail($user);
        $lines = implode(' ', $mail->introLines);
        $this->assertStringContainsString('Nalutas', $lines);
        $this->assertStringContainsString('CMP-2026-42', $lines);
        $this->assertStringContainsString('Keep this English resident remark.', $lines);
        $this->assertStringContainsString('Pending English Content', $mail->greeting);
    }

    public function test_workflow_notification_translates_the_actual_observer_record_types(): void
    {
        app()->setLocale('fil');
        $user = User::factory()->make();
        foreach (['complaint' => 'reklamo', 'request' => 'kahilingan'] as $type => $translated) {
            $mail = (new WorkflowStatusChanged($type, 'Pending English reference', 'Resolved'))->toMail($user);
            $this->assertStringContainsString($translated, $mail->subject);
            $this->assertStringContainsString($translated, implode(' ', $mail->introLines));
            $this->assertStringContainsString('Pending English reference', implode(' ', $mail->introLines));
        }
    }

    public function test_filipino_report_status_labels_keep_canonical_filter_values(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['preferred_locale' => 'fil']);
        $user->assignRole('hoa_admin');
        $this->actingAs($user, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app()->setLocale('fil');

        Livewire::test(Reports::class)
            ->set('selectedCategory', 'Operations')
            ->assertSeeHtml('<option value="Resolved" data-i18n="Resolved">Nalutas</option>')
            ->assertDontSeeHtml('value="Nalutas"')
            ->set('filters.complaints.status', 'Resolved')
            ->call('previewReport', 'complaints')
            ->assertHasNoErrors()
            ->assertSet('previewSlug', 'complaints')
            ->assertSet('filters.complaints.status', 'Resolved');
    }

    public function test_shared_submission_messages_have_filipino_translations(): void
    {
        foreach ([CaseAttachmentRules::class, ComplaintSubmissionValidator::class, ServiceRequestSubmissionValidator::class, HomeownerProfileValidator::class] as $validator) {
            app()->setLocale('en');
            $english = app($validator)->messages();
            app()->setLocale('fil');
            $filipino = app($validator)->messages();
            foreach ($english as $field => $message) {
                $this->assertNotSame($message, $filipino[$field], $validator.' '.$field);
            }
        }
    }

    public function test_localized_contact_submission_preserves_entered_content(): void
    {
        Notification::fake();
        $this->withSession(['locale' => 'fil'])->postJson('/contact', [
            'name' => 'Pending English Name', 'email' => 'contact@example.test', 'subject' => 'Keep this English subject', 'message' => 'Keep the resident message unchanged.',
        ])->assertCreated()->assertJsonPath('data.message', 'Salamat. Natanggap na ng tanggapan ng HOA ang iyong mensahe.');
        $this->assertDatabaseHas('contact_messages', ['name' => 'Pending English Name', 'subject' => 'Keep this English subject', 'message' => 'Keep the resident message unchanged.']);
    }

    public function test_native_version_check_accepts_only_mysql_84(): void
    {
        foreach (['8.4.0', '8.4.6-commercial', '8.4.7-0ubuntu0.24.04.1'] as $version) {
            NativeMysqlAcceptance::assertVersion($version);
            $this->addToAssertionCount(1);
        }
        foreach (['10.4.32-MariaDB', '8.0.40', '9.1.0', '8.4.0-MariaDB', 'unverified'] as $version) {
            try {
                NativeMysqlAcceptance::assertVersion($version);
                $this->fail('Unexpected server accepted: '.$version);
            } catch (DomainException $exception) {
                $this->assertStringContainsString('Native MySQL 8.4', $exception->getMessage());
            }
        }
    }

    public function test_native_preflight_refuses_non_disposable_targets_before_connecting(): void
    {
        $previous = config('database.default');
        $database = config('database.connections.mysql.database');
        try {
            config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'hoa_system']);
            $this->artisan('hoa:verify-native-mysql')->assertFailed();
        } finally {
            // RefreshDatabase must still roll back its original connection at teardown.
            config(['database.default' => $previous, 'database.connections.mysql.database' => $database]);
        }
    }
}
