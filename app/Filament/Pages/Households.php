<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Households\ManageHousehold;
use App\Actions\Households\ManageHouseholdHead;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Schemas\HouseholdHeadReview;
use App\Models\Homeowner;
use App\Models\Household;
use App\Support\ActionFeedback;
use App\Support\AuthenticatedActor;
use App\Support\CurrentPropertyAddress;
use App\Support\OperationalAccess;
use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use App\Support\PropertyPhase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
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
                TextColumn::make('identifier')->label(__('Household reference'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('full_address')->label(__('Property'))->state(fn (Household $record) => CurrentPropertyAddress::concise($record))->searchable(['block', 'lot'])->wrap(),
                TextColumn::make('members_count')->label(__('Current members')), TextColumn::make('needs_review')->label(__('Review'))->state(fn (Household $record): string => __($record->needs_review || app(ManageHouseholdHead::class)->status($record)['warning'] ? 'Review required' : 'Confirmed'))->badge()->color(fn ($state): string => $state === __('Review required') ? 'warning' : 'success'),
                TextColumn::make('current_head')->label(__('Household head'))->state(fn (Household $record): string => app(ManageHouseholdHead::class)->status($record)['head_name'] ?? __('Not assigned'))->wrap()
                    ->description(fn (Household $record): ?string => app(ManageHouseholdHead::class)->status($record)['warning']),
            ])->filters([TrashedFilter::make()->visible(fn (): bool => app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin') ?? false)])
            ->recordActions([
                Action::make('editHousehold')->label(__('Edit household'))->icon('heroicon-o-pencil-square')->button()->tooltip(__('Edit household'))->modalWidth('2xl')->fillForm(fn (Household $record): array => [...$record->getAttributes(), ...HouseholdHeadReview::data($record), 'review_head' => false])->schema($this->householdFields())
                    ->visible(fn (Household $record): bool => ! $record->trashed())
                    ->action(function (Household $record, array $data): void {
                        $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->save($record->id, $data, app(AuthenticatedActor::class)->user()));
                        Notification::make()->success()->title(__(($data['review_head'] ?? false) ? 'Household Head and Relationships Confirmed Successfully!' : 'Household Information Updated Successfully!'))->send();
                    }),
                ActionGroup::make([
                    Action::make('clearHouseholdHead')->label(__('Clear Household Head'))->requiresConfirmation()
                        ->modalDescription(__('Current Head labels will become Other. Historical memberships will stay unchanged and the household will require HOA review.'))
                        ->visible(fn (Household $record): bool => ! $record->trashed() && ($record->head_member_id !== null || $record->members()->whereNull('ended_at')->where('relationship', 'Head')->exists()))
                        ->fillForm(fn (Household $record): array => ['head_revision' => app(ManageHouseholdHead::class)->revision($record)])
                        ->schema([Hidden::make('head_revision')])
                        ->action(function (Household $record, array $data): void {
                            $this->runValidatedDomainAction(fn () => app(ManageHouseholdHead::class)->clear($record->id, $data['head_revision'] ?? '', app(AuthenticatedActor::class)->user()));
                            ActionFeedback::success('Household Head Cleared Successfully!');
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
                        ->action(fn (Household $record) => ActionFeedback::after('Household Archived Successfully!', fn () => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->archive('household', $record->id, false, app(AuthenticatedActor::class)->user())))),
                    Action::make('restore')->label(__('Restore'))->requiresConfirmation()->visible(fn (Household $record): bool => $record->trashed() && app(AuthenticatedActor::class)->user()?->hasRole('hoa_admin'))
                        ->action(fn (Household $record) => ActionFeedback::after('Household Restored Successfully!', fn () => $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->archive('household', $record->id, true, app(AuthenticatedActor::class)->user())))),
                ])->label(__('More actions'))->tooltip(__('More actions'))->icon('heroicon-o-ellipsis-horizontal')->button()->extraAttributes(['data-hoa-record-action' => 'true']),
            ]);
    }

    private function householdFields(): array
    {
        return [
            Select::make('phase_suffix')->label(__('Phase'))->options(fn ($record): array => PropertyPhase::options() + ($record && filled($record->phase_suffix) ? [$record->phase_suffix => $record->phase_suffix] : []))->required(fn ($record): bool => $record === null)->disabled(fn ($record): bool => $record !== null),
            TextInput::make('block')->required()->maxLength(20), TextInput::make('lot')->required()->maxLength(20), Hidden::make('phase')->default(PropertyAddress::DEFAULT_PHASE),
            TextInput::make('contact_number')->label(__('Household contact number'))->maxLength(20), TextInput::make('occupancy')->maxLength(255),
            Select::make('registered_homeowner_id')->label(__('Registered Owner / Co-owner'))->options(fn ($record): array => $record ? PropertyIdentity::scope(Homeowner::query()->with('user'), $record)->whereIn('ownership_type', ['Owner', 'Co-owner'])->get()->mapWithKeys(fn ($h) => [$h->id => $h->user->full_name])->all() : []),
            Toggle::make('review_head')->label(__('Review household head and all relationships'))->live()->default(false)->visible(fn ($record): bool => $record !== null),
            Group::make()->visible(fn ($record): bool => $record !== null)->schema(fn ($record): array => $record
                ? HouseholdHeadReview::fields(fn (): Household => $record, fn (Get $get): bool => (bool) $get('review_head')) : []),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('createHousehold')->label(__('Add household'))->schema($this->householdFields())->action(function (array $data): void {
            $this->runValidatedDomainAction(fn () => app(ManageHousehold::class)->save(null, $data, app(AuthenticatedActor::class)->user()));
            Notification::make()->success()->title(__('Household Created Successfully!'))->send();
        })];
    }
}
