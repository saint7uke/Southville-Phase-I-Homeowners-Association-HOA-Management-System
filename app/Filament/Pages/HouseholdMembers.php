<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Households\ManageHousehold;
use App\Filament\Concerns\MapsDomainValidation;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Livewire\Attributes\Locked;

final class HouseholdMembers extends Page implements HasTable
{
    use InteractsWithTable;
    use MapsDomainValidation;

    protected string $view = 'filament.pages.household-members';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int $householdId;

    public static function canAccess(): bool
    {
        return OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_homeowners');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->householdId = (int) request()->query('household');
        Household::withTrashed()->findOrFail($this->householdId);
    }

    private function fields(): array
    {
        return [TextInput::make('full_name')->required()->maxLength(255), Select::make('relationship')->options(array_combine(ManageHousehold::RELATIONSHIPS, ManageHousehold::RELATIONSHIPS))->default('Other')->required(),
            DatePicker::make('date_of_birth')->maxDate(today()->subDay()), Select::make('sex')->options(['Male' => __('Male'), 'Female' => __('Female'), 'Prefer not to say' => __('Prefer not to say')]),
            Select::make('residency_status')->options(array_combine(ManageHousehold::RESIDENCY, ManageHousehold::RESIDENCY))->default('Resident')->required()];
    }

    public function table(Table $table): Table
    {
        abort_unless(self::canAccess(), 403);

        return $table->query(HouseholdMember::query()->where('household_id', $this->householdId))->columns([
            TextColumn::make('full_name')->searchable(), TextColumn::make('relationship')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state)), TextColumn::make('residency_status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state)),
            TextColumn::make('date_of_birth')->date(), TextColumn::make('ended_at')->label(__('Membership ended'))->dateTime(),
        ])->filters([TrashedFilter::make()->visible(fn (): bool => app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin') ?? false)])->recordActions([
            Action::make('editMember')->label(__('Edit'))->fillForm(fn (HouseholdMember $record): array => $record->only(['full_name', 'relationship', 'date_of_birth', 'sex', 'residency_status']))->schema($this->fields())->visible(fn (HouseholdMember $record): bool => ! $record->trashed())
                ->action(fn (HouseholdMember $record, array $data) => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->saveMember($this->householdId, $record->id, $data, app(AuthenticatedActor::class)->user()))),
            Action::make('archiveMember')->label(__('Archive'))->requiresConfirmation()->visible(fn (HouseholdMember $record): bool => ! $record->trashed() && app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin'))
                ->action(fn (HouseholdMember $record) => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->archive('member', $record->id, false, app(AuthenticatedActor::class)->user()))),
            Action::make('restoreMember')->label(__('Restore'))->requiresConfirmation()->visible(fn (HouseholdMember $record): bool => $record->trashed() && app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin'))
                ->action(fn (HouseholdMember $record) => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->archive('member', $record->id, true, app(AuthenticatedActor::class)->user()))),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('addMember')->label(__('Add resident'))->schema($this->fields())->action(fn (array $data) => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->saveMember($this->householdId, null, $data, app(AuthenticatedActor::class)->user())))];
    }
}
