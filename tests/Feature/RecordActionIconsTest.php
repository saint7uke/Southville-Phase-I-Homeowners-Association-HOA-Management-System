<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Households;
use App\Filament\Pages\Projects;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Support\RecordActionIcons;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class RecordActionIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_actions_keep_labels_confirmations_and_disabled_state(): void
    {
        $action = RecordActionIcons::action(Action::make('approve')->label('Approve')->color('success')->disabled()->requiresConfirmation());
        $this->assertFalse($action->isIconButton());
        $this->assertSame('Approve', $action->getLabel());
        $this->assertSame('Approve', $action->getTooltip());
        $this->assertSame('heroicon-o-check-circle', $action->getIcon());
        $this->assertTrue($action->isDisabled());
        $this->assertTrue($action->isConfirmationRequired());
        $this->assertSame('success', $action->getColor());
        $this->assertFalse(Action::make('save')->button()->isIconButton());
    }

    public function test_archive_and_permanent_delete_have_distinct_icons(): void
    {
        $record = new User;
        $this->assertSame('heroicon-o-archive-box', RecordActionIcons::action(DeleteAction::make()->record($record))->getIcon());
        $this->assertSame('heroicon-o-trash', RecordActionIcons::action(ForceDeleteAction::make()->record($record))->getIcon());
        $this->assertSame('heroicon-o-arrow-uturn-left', RecordActionIcons::action(RestoreAction::make())->getIcon());
    }

    public function test_default_action_colors_follow_primary_success_warning_and_danger_semantics(): void
    {
        $this->assertSame('primary', RecordActionIcons::action(Action::make('saveProfile'))->getColor());
        $this->assertSame('success', RecordActionIcons::action(Action::make('approve'))->getColor());
        $this->assertSame('warning', RecordActionIcons::action(Action::make('assignHouseholdHead'))->getColor());
        $this->assertSame('danger', RecordActionIcons::action(Action::make('archiveRecord'))->getColor());
    }

    public function test_group_items_stay_labeled_and_existing_explanations_are_preserved(): void
    {
        $child = Action::make('archive')->label('Archive');
        $group = RecordActionIcons::group(ActionGroup::make([$child])->label('More actions'));
        $this->assertFalse($group->isIconButton());
        $this->assertFalse($child->isIconButton());
        $this->assertSame('Archive', $child->getLabel());
        $action = RecordActionIcons::action(Action::make('receiptSetup')->tooltip('Contact the administrator.'));
        $this->assertSame('Contact the administrator.', $action->getTooltip());
    }

    public function test_bulk_action_menu_entries_remain_unchanged(): void
    {
        $action = BulkAction::make('delete')->label('Delete');
        $configured = RecordActionIcons::action($action);

        $this->assertSame($action, $configured);
        $this->assertFalse($configured->isIconButton());
        $this->assertSame('Delete', $configured->getLabel());
        $this->assertFalse($configured->hasIcon());
    }

    public function test_custom_control_escapes_labels_and_keeps_button_type_and_link_destination(): void
    {
        $html = Blade::render('<x-record-icon :label="$label" wire:click="openRecord" wire:loading.attr="disabled" />', ['label' => '\"><script>alert(1)</script>']);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('wire:click="openRecord"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $html = Blade::render('<x-record-icon :label="__(\'Download\')" href="/safe-download" />');
        $this->assertStringContainsString('href="/safe-download"', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
    }

    public function test_translated_custom_controls_have_readable_names_and_visible_labels(): void
    {
        app()->setLocale('fil');
        $html = Blade::render('<x-record-icon :label="__(\'View details\')" label-key="View details" />');
        $this->assertStringContainsString('aria-label="Tingnan ang mga detalye"', $html);
        $this->assertStringContainsString('data-i18n-aria-label="View details"', $html);
        $this->assertStringContainsString('data-i18n="View details" >Tingnan ang mga detalye</span>', $html);
    }

    public function test_admin_and_staff_record_rows_keep_visible_labels_and_create_controls(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Http::preventStrayRequests();
        foreach (['admin' => 'hoa_admin', 'staff' => 'hoa_staff'] as $panel => $role) {
            $user = User::factory()->create(['account_status' => 'Active']);
            $user->assignRole($role);
            Filament::setCurrentPanel(Filament::getPanel($panel));
            $this->actingAs($user, $panel);
            $page = Livewire::test(Households::class)->assertSuccessful()->instance();
            foreach ($page->getTable()->getRecordActions() as $action) {
                $this->assertFalse($action->isIconButton());
                $this->assertNotEmpty($action->getLabel());
            }
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::role('hoa_admin')->firstOrFail();
        $this->actingAs($admin, 'admin');
        $page = Livewire::test(Projects::class)->assertSuccessful()->instance();
        foreach ($page->getTable()->getRecordActions() as $action) {
            $this->assertFalse($action->isIconButton());
        }
        foreach ($page->getCachedHeaderActions() as $action) {
            $this->assertFalse($action->isIconButton());
        }
        $page = Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])->assertSuccessful()->instance();
        foreach ($page->getCachedHeaderActions() as $action) {
            $this->assertFalse($action->isIconButton());
        }
    }
}
