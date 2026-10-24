<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Pages;

use App\Actions\Households\ManageHousehold;
use App\Models\Household;
use App\Services\OwnHouseholdSummary;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;

final class MyHousehold extends Page implements HasTable
{
    use InteractsWithTable;

    private const PER_PAGE = 10;

    #[Locked]
    public ?int $householdContextId = null;

    protected ?Household $resolvedHousehold = null;

    protected bool $householdResolved = false;

    protected ?int $currentMemberCount = null;

    protected string $view = 'filament.homeowner.pages.my-household';

    protected static ?string $navigationLabel = 'My Household';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = 'My Home';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && ! $user->trashed() && $user->account_status === 'Active' && $user->hasRole('homeowner') && $user->homeowner()->exists();
    }

    protected function getViewData(): array
    {
        $household = $this->ownHousehold();

        return ['summary' => $household ? [
            'id' => $household->id, 'identifier' => $household->identifier,
            'address' => $household->full_address, 'needs_review' => $household->needs_review,
            'members_count' => $this->memberCount(),
        ] : null];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(OwnHouseholdSummary::class)->membersQuery($this->ownHousehold()))
            ->columns([
                Split::make([
                    TextColumn::make('full_name')->label(__('Name'))->weight('semibold')->wrap()
                        ->description(fn (): HtmlString => $this->translatedLabel('Name'), position: 'above'),
                    TextColumn::make('relationship')->label(__('Relationship'))->wrap()
                        ->description(fn (): HtmlString => $this->translatedLabel('Relationship'), position: 'above')
                        ->formatStateUsing(fn (string $state): string|HtmlString => in_array($state, ManageHousehold::RELATIONSHIPS, true) ? $this->translatedLabel($state) : $state),
                    TextColumn::make('residency_status')->label(__('Residency status'))->badge()->wrap()
                        ->description(fn (): HtmlString => $this->translatedLabel('Residency status'), position: 'above')
                        ->formatStateUsing(fn (string $state): string|HtmlString => in_array($state, ManageHousehold::RESIDENCY, true) ? $this->translatedLabel($state) : $state)
                        ->color(fn (string $state): string => $state === 'Resident' ? 'success' : 'gray'),
                ])->from('md'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('full_name')->orderBy('id'))
            ->paginated(fn (): bool => $this->memberCount() > self::PER_PAGE)
            ->paginationPageOptions([self::PER_PAGE])->defaultPaginationPageOption(self::PER_PAGE)
            ->persistRecordsPerPageInSession(false)->scrollToTopOnPageChange()
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading(fn (): HtmlString => $this->translatedLabel('No current household members'))
            ->emptyStateDescription(fn (): HtmlString => $this->translatedLabel('Contact the HOA office if household members are missing.'))
            ->recordActions([])->toolbarActions([])->recordUrl(null);
    }

    public function getTableRecordsPerPage(): int
    {
        // Do not allow forged per-page values to bypass the bounded member query.
        return self::PER_PAGE;
    }

    protected function paginateTableQuery(Builder $query): LengthAwarePaginator
    {
        $total = $query->toBase()->getCountForPagination();
        $page = max(1, min((int) $this->getTablePage(), (int) max(1, ceil($total / self::PER_PAGE))));
        if ($page !== (int) $this->getTablePage()) {
            $this->setPage($page, $this->getTablePaginationPageName());
        }

        return $query->paginate(perPage: self::PER_PAGE, pageName: $this->getTablePaginationPageName(), page: $page, total: $total)->onEachSide(0);
    }

    private function ownHousehold(): ?Household
    {
        if (! $this->householdResolved) {
            $this->householdResolved = true;
            $this->resolvedHousehold = app(OwnHouseholdSummary::class)->householdFor(Filament::auth()->user());
            if ($this->householdContextId !== $this->resolvedHousehold?->id) {
                $this->householdContextId = $this->resolvedHousehold?->id;
                $this->resetPage($this->getTablePaginationPageName());
            }
        }

        return $this->resolvedHousehold;
    }

    private function memberCount(): int
    {
        $count = $this->currentMemberCount ??= app(OwnHouseholdSummary::class)->membersQuery($this->ownHousehold())->count();
        if ($count <= self::PER_PAGE && (int) $this->getTablePage() !== 1) {
            $this->resetPage($this->getTablePaginationPageName());
        }

        return $count;
    }

    private function translatedLabel(string $key): HtmlString
    {
        return new HtmlString('<span data-i18n="'.e($key).'">'.e(__($key)).'</span>');
    }
}
