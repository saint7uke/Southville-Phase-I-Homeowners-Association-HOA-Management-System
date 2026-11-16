<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Households\ManageHousehold;
use App\Actions\Households\ManageHouseholdHead;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Schemas\HouseholdHeadReview;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Support\ActionFeedback;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use App\Support\RecordActionIcons;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
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
        return [TextInput::make('full_name')->required()->maxLength(255), Select::make('relationship')->label(__('Relationship to household head'))
            ->options(function (?HouseholdMember $record): array {
                return $record && $record->relationship === 'Head' && ($record->ended_at !== null || $this->household()->head_member_id === $record->id)
                    ? ['Head' => __('Head')] : array_combine(ManageHouseholdHead::RELATIONSHIPS, array_map(__(...), ManageHouseholdHead::RELATIONSHIPS));
            })->disabled(fn (?HouseholdMember $record): bool => $record && ($record->ended_at !== null || $this->household()->head_member_id === $record->id))
            ->dehydrated(true)->default('Other')->required(),
            DatePicker::make('date_of_birth')->maxDate(today()->subDay()), Select::make('sex')->options(['Male' => __('Male'), 'Female' => __('Female'), 'Prefer not to say' => __('Prefer not to say')]),
            Select::make('residency_status')->options(array_combine(ManageHousehold::RESIDENCY, ManageHousehold::RESIDENCY))->default('Resident')->required()
                ->helperText(fn (?HouseholdMember $record): ?string => $record?->ended_at && $record->relationship === 'Head'
                    ? __('A returning former head is recorded as Other until a new household head review.') : null)];
    }

    public function household(): Household
    {
        abort_unless(self::canAccess(), 403);

        return Household::withTrashed()->findOrFail($this->householdId);
    }

    public function headStatus(): array
    {
        return app(ManageHouseholdHead::class)->status($this->household());
    }

    public function table(Table $table): Table
    {
        abort_unless(self::canAccess(), 403);

        return $table->query(HouseholdMember::query()->where('household_id', $this->householdId))->columns([
            TextColumn::make('full_name')->searchable(), TextColumn::make('relationship')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state)), TextColumn::make('residency_status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state)),
            TextColumn::make('date_of_birth')->date(), TextColumn::make('ended_at')->label(__('Membership ended'))->dateTime(),
        ])->filters([TrashedFilter::make()->visible(fn (): bool => app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin') ?? false)])->recordActions([
            Action::make('setHouseholdHead')->label(__('Assign head'))->icon('heroicon-o-user-circle')->button()->tooltip(__('Assign head'))->modalWidth('2xl')
                ->visible(fn (HouseholdMember $record): bool => ! $this->household()->trashed() && app(ManageHouseholdHead::class)->eligible($record))
                ->fillForm(fn (HouseholdMember $record): array => HouseholdHeadReview::data($this->household(), $record->id))
                ->schema(HouseholdHeadReview::fields(fn (): Household => $this->household()))
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(ManageHouseholdHead::class)->assign($this->householdId, $data, app(AuthenticatedActor::class)->user()));
                    ActionFeedback::success('Household Head and Relationships Confirmed Successfully!');
                }),
            Action::make('editMember')->label(__('Edit'))->fillForm(fn (HouseholdMember $record): array => $record->only(['full_name', 'relationship', 'date_of_birth', 'sex', 'residency_status']))->schema($this->fields())->visible(fn (HouseholdMember $record): bool => ! $record->trashed() && ! $this->household()->trashed())
                ->action(fn (HouseholdMember $record, array $data) => ActionFeedback::after('Resident Information Updated Successfully!', fn () => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->saveMember($this->householdId, $record->id, $data, app(AuthenticatedActor::class)->user())))),
            Action::make('archiveMember')->label(__('Archive'))->requiresConfirmation()->visible(fn (HouseholdMember $record): bool => ! $record->trashed() && app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin'))
                ->action(fn (HouseholdMember $record) => ActionFeedback::after('Resident Archived Successfully!', fn () => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->archive('member', $record->id, false, app(AuthenticatedActor::class)->user())))),
            Action::make('restoreMember')->label(__('Restore'))->requiresConfirmation()->visible(fn (HouseholdMember $record): bool => $record->trashed() && app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin'))
                ->action(fn (HouseholdMember $record) => ActionFeedback::after('Resident Restored Successfully!', fn () => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->archive('member', $record->id, true, app(AuthenticatedActor::class)->user())))),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('addMember')->label(__('Add resident'))->visible(fn (): bool => ! $this->household()->trashed())->schema($this->fields())->action(fn (array $data) => ActionFeedback::after('Resident Created Successfully!', fn () => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->saveMember($this->householdId, null, $data, app(AuthenticatedActor::class)->user())))),
            RecordActionIcons::action(Action::make('clearHouseholdHead')->label(__('Clear Household Head'))->requiresConfirmation()
                ->modalDescription(__('Current Head labels will become Other. Historical memberships will stay unchanged and the household will require HOA review.'))
                ->visible(fn (): bool => ! $this->household()->trashed() && ($this->household()->head_member_id !== null || $this->household()->members()->whereNull('ended_at')->where('relationship', 'Head')->exists()))
                ->fillForm(fn (): array => ['head_revision' => app(ManageHouseholdHead::class)->revision($this->household())])->schema([Hidden::make('head_revision')])
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(ManageHouseholdHead::class)->clear($this->householdId, $data['head_revision'] ?? '', app(AuthenticatedActor::class)->user()));
                    ActionFeedback::success('Household Head Cleared Successfully!');
                })),
        ];
    }
}
