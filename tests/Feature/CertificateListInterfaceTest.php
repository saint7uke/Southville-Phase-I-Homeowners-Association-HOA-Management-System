<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\CohabitationCertificateSource;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\UsesSyntheticCohabitation;
use Tests\TestCase;

final class CertificateListInterfaceTest extends TestCase
{
    use RefreshDatabase;
    use UsesSyntheticCohabitation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        config(['certificates.cohabitation_source_directory' => Storage::disk('local')->path('missing-cohabitation-source')]);
    }

    public function test_admin_header_has_two_daily_actions_and_one_setup_menu(): void
    {
        $this->login('hoa_admin', 'admin');
        $page = Livewire::test(ListCertificates::class)
            ->assertActionVisible('create')->assertActionVisible('findPaymentReceipt')
            ->assertActionVisible('confirmResidencySource')->assertActionVisible('confirmButawSource')
            ->assertActionVisible('setupCohabitationSource')->assertSee('Awaiting corrected document');
        $header = $page->instance()->getCachedHeaderActions();
        $this->assertCount(3, $header);
        $this->assertSame('New document', $header[0]->getLabel());
        $this->assertSame('Monthly receipts', $header[1]->getLabel());
        $this->assertInstanceOf(ActionGroup::class, $header[2]);
        $this->assertSame('Document setup', $header[2]->getLabel());
        $this->assertNull($header[1]->getModalSubmitAction());
        $this->assertDatabaseCount('certificate_templates', 0);
        $this->assertDatabaseCount('payment_document_sources', 0);
    }

    public function test_staff_can_use_daily_actions_but_not_source_setup_even_by_direct_mount(): void
    {
        $this->login('hoa_staff', 'staff');
        $page = Livewire::test(ListCertificates::class)->assertActionVisible('create')->assertActionVisible('findPaymentReceipt');
        foreach (['reviewResidencySource', 'confirmResidencySource', 'reviewButawSource', 'confirmButawSource', 'setupCohabitationSource'] as $name) {
            $page->assertActionHidden($name);
        }
        $this->assertTrue($page->instance()->getCachedHeaderActions()[2]->isHidden());
        $page->call('mountAction', 'confirmResidencySource')->assertSet('mountedActions', []);
        $page->call('mountAction', 'setupCohabitationSource')->assertSet('mountedActions', []);
        $this->assertDatabaseCount('certificate_templates', 0);
    }

    public function test_one_combined_cohabitation_review_hides_after_confirmation_including_a_new_visit(): void
    {
        $actor = $this->login('hoa_admin', 'admin');
        $this->travelTo(now()->setDate(2026, 10, 21));
        $this->syntheticCohabitationSource();
        $page = Livewire::test(ListCertificates::class)->assertActionVisible('setupCohabitationSource')
            ->mountAction('setupCohabitationSource')->assertSee('Ready for review');
        // Filament's action modals are partial responses, not part of the cached page HTML.
        $schema = $page->instance()->getSchema('mountedActionSchema0');
        $modal = $schema->getComponent(fn ($component) => $component instanceof Placeholder && $component->getName() === 'source_notice')->getContent()->toHtml();
        $this->assertStringContainsString(route('certificates.cohabitation-reference'), $modal);
        $this->assertTrue($schema->getComponent(fn ($component) => $component instanceof Toggle && $component->getName() === 'reviewed', withHidden: true)->isVisible());
        $page->setActionData(['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic approval evidence for this test only.'])
            ->callMountedAction()
            ->assertHasNoActionErrors()->assertActionHidden('setupCohabitationSource');
        $this->assertSame('Approved', CertificateTemplate::sole()->status);
        Livewire::test(ListCertificates::class)->assertActionHidden('setupCohabitationSource')->assertActionVisible('create');
        $this->assertSame('confirmed', app(CohabitationCertificateSource::class)->setupStatus()['state']);
    }

    public function test_missing_source_modal_has_guidance_but_no_review_link_or_confirmation_submit(): void
    {
        $this->login('hoa_admin', 'admin');
        $page = Livewire::test(ListCertificates::class)->mountAction('setupCohabitationSource')->assertSee('Awaiting corrected document');
        $schema = $page->instance()->getSchema('mountedActionSchema0');
        $modal = $schema->getComponent(fn ($component) => $component instanceof Placeholder && $component->getName() === 'source_notice')->getContent()->toHtml();
        foreach (['Corrected original DOCX containing Phase 1 - ____.', 'Reviewed PDF exported from the corrected original.', 'Reviewed blank-field mapping for that PDF.'] as $requirement) {
            $this->assertStringContainsString($requirement, $modal);
        }
        $this->assertStringNotContainsString(route('certificates.cohabitation-reference'), $modal);
        $this->assertTrue($schema->getComponent(fn ($component) => $component instanceof Toggle && $component->getName() === 'reviewed', withHidden: true)->isHidden());
        $this->assertNull($page->instance()->getMountedAction()->getModalSubmitAction());
        $this->assertDatabaseCount('certificate_templates', 0);
        $this->assertDatabaseCount('certificate_previews', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('certificate-backgrounds'));
    }

    public function test_missing_source_cannot_be_confirmed_by_bypassing_the_modal(): void
    {
        $actor = $this->login('hoa_admin', 'admin');
        try {
            app(CohabitationCertificateSource::class)->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'Forged missing-source approval.'], $actor);
            $this->fail('Missing assets must still prevent approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
            $this->assertDatabaseCount('certificate_templates', 0);
        }
    }

    public function test_filipino_setup_guidance_does_not_change_the_uploaded_document(): void
    {
        $actor = $this->login('hoa_admin', 'admin');
        $actor->forceFill(['preferred_locale' => 'fil'])->save();
        $this->get(route('certificates.cohabitation-reference'))->assertStatus(409)
            ->assertSee('Hinihintay ang itinamang dokumento')
            ->assertSee('Itinamang orihinal na DOCX na may Phase 1 - ____.')
            ->assertSee('Bumalik sa Mga Sertipiko')->assertDontSee('Stack Trace');
        $this->assertSame(CohabitationCertificateSource::UNADJUSTED_SOURCE_SHA256, hash_file('sha256', resource_path('certificates/cohabitation-2026/original-unadjusted.docx')));
        $this->assertDatabaseCount('certificate_templates', 0);
    }

    public function test_a_different_source_version_requires_fresh_confirmation_without_changing_the_old_one(): void
    {
        $actor = $this->login('hoa_admin', 'admin');
        $layout = $this->syntheticCohabitationSource();
        $source = app(CohabitationCertificateSource::class);
        $source->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic confirmation for the initial source only.'], $actor);
        $previous = CertificateTemplate::sole()->getAttributes();
        Storage::disk('local')->put('synthetic-cohabitation/original.docx', 'DIFFERENT SYNTHETIC CORRECTED SOURCE');
        $layout['source_sha256'] = hash('sha256', 'DIFFERENT SYNTHETIC CORRECTED SOURCE');
        Storage::disk('local')->put('synthetic-cohabitation/layout.json', json_encode($layout, JSON_THROW_ON_ERROR));
        $this->assertSame('ready', $source->setupStatus()['state']);
        Livewire::test(ListCertificates::class)->assertActionVisible('setupCohabitationSource')->assertSee('Ready for review');
        $this->assertSame($previous, CertificateTemplate::sole()->getAttributes());
        $this->assertDatabaseCount('certificate_templates', 1);
    }

    public function test_font_and_mapping_problems_remain_setup_requirements_and_do_not_approve_anything(): void
    {
        $this->login('hoa_admin', 'admin');
        $this->syntheticCohabitationSource();
        config(['certificates.cohabitation_font' => 'missing-licensed-arial.ttf']);
        $status = app(CohabitationCertificateSource::class)->setupStatus();
        $this->assertSame('awaiting', $status['state']);
        $this->assertContains(__('Install the licensed Arial font before generating Cohabitation certificates.'), $status['requirements']);
        Storage::disk('local')->put('synthetic-cohabitation/layout.json', '{INVALID JSON');
        $this->assertSame('awaiting', app(CohabitationCertificateSource::class)->setupStatus()['state']);
        $this->assertDatabaseCount('certificate_templates', 0);
        $this->assertDatabaseCount('certificates', 0);
    }

    private function login(string $role, string $panel): User
    {
        $actor = User::factory()->create(['account_status' => 'Active']);
        $actor->assignRole($role);
        $this->actingAs($actor, $panel);
        Filament::setCurrentPanel($panel);

        return $actor;
    }
}
