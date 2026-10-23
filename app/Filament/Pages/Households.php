<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Households\ManageHousehold;
use App\Filament\Concerns\MapsDomainValidation;
use App\Models\Homeowner;
use App\Models\Household;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use App\Support\PropertyIdentity;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class Households extends Page implements HasTable
{
    use InteractsWithTable;
    use MapsDomainValidation;

    protected string $view = 'filament.pages.households';

    protected static ?string $navigationLabel = 'Households & Provisional RBI';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function canAccess(): bool
    {
        return OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_homeowners');
    }

    public function table(Table $table): Table
    {
        abort_unless(self::canAccess(), 403);

        return $table->query(Household::query()->withCount(['members' => fn ($q) => $q->whereNull('ended_at')]))
            ->columns([
                TextColumn::make('identifier')->label(__('Household ID'))->searchable(),
                TextColumn::make('full_address')->label(__('Property'))->searchable(['house_number', 'street', 'block', 'lot']),
                TextColumn::make('members_count')->label(__('Current members')), TextColumn::make('needs_review')->label(__('Needs HOA review'))->badge(),
            ])->filters([TrashedFilter::make()->visible(fn (): bool => app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin') ?? false)])
            ->recordActions([
                Action::make('editHousehold')->label(__('Edit household'))->fillForm(fn (Household $record): array => $record->getAttributes())->schema($this->householdFields())
                    ->visible(fn (Household $record): bool => ! $record->trashed())
                    ->action(function (Household $record, array $data): void {
                        $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->save($record->id, $data, app(AuthenticatedActor::class)->user()));
                        Notification::make()->success()->title(__('Changes saved'))->send();
                    }),
                Action::make('members')->label(__('View / manage members'))->url(fn (Household $record): string => HouseholdMembers::getUrl(['household' => $record->id])),
                Action::make('pdf')->label(__('Provisional RBI PDF'))
                    ->url(fn (Household $record): string => Filament::getCurrentPanel()?->getId() === 'admin'
                        ? route('admin.households.export', ['household' => $record, 'format' => 'pdf']) : route('households.pdf', $record))
                    ->openUrlInNewTab()->visible(fn (Household $record): bool => ! $record->trashed()),
                Action::make('excel')->label(__('Provisional RBI Excel'))
                    ->url(fn (Household $record): string => route('admin.households.export', ['household' => $record, 'format' => 'xlsx']))
                    ->visible(fn (Household $record): bool => ! $record->trashed() && Filament::getCurrentPanel()?->getId() === 'admin'
                        && OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_homeowners', true)),
                Action::make('archive')->label(__('Archive'))->color('danger')->requiresConfirmation()->visible(fn (Household $record): bool => ! $record->trashed() && app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin'))
                    ->action(fn (Household $record) => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->archive('household', $record->id, false, app(AuthenticatedActor::class)->user()))),
                Action::make('restore')->label(__('Restore'))->requiresConfirmation()->visible(fn (Household $record): bool => $record->trashed() && app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin'))
                    ->action(fn (Household $record) => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->archive('household', $record->id, true, app(AuthenticatedActor::class)->user()))),
            ]);
    }

    private function householdFields(): array
    {
        return [
            TextInput::make('house_number')->maxLength(50), TextInput::make('street')->required()->maxLength(100),
            TextInput::make('phase_suffix')->label(__('Phase suffix'))->prefix('Phase 1 -')->maxLength(10)->readOnly(fn ($record): bool => $record !== null),
            TextInput::make('block')->required()->maxLength(20), TextInput::make('lot')->required()->maxLength(20), TextInput::make('phase')->required()->maxLength(50),
            TextInput::make('contact_number')->label(__('Household contact number'))->maxLength(20), TextInput::make('occupancy')->maxLength(255),
            Select::make('registered_homeowner_id')->label(__('Registered Owner / Co-owner'))->options(fn ($record): array => $record ? PropertyIdentity::scope(Homeowner::query()->with('user'), $record)->whereIn('ownership_type', ['Owner', 'Co-owner'])->get()->mapWithKeys(fn ($h) => [$h->id => $h->user->full_name])->all() : []),
            Select::make('head_member_id')->label(__('Household head'))->options(fn ($record): array => $record ? $record->members()->whereNull('ended_at')->pluck('full_name', 'id')->all() : []),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('createHousehold')->label(__('Add household'))->schema($this->householdFields())->action(function (array $data): void {
            $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->save(null, $data, app(AuthenticatedActor::class)->user()));
            Notification::make()->success()->title(__('Household saved'))->send();
        })];
    }
}
