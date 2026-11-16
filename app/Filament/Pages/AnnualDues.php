<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Dues\PrepareMonthlyAssessment;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Services\AnnualDuesSummary;
use App\Support\AuthenticatedActor;
use App\Support\OperationalAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class AnnualDues extends Page
{
    protected string $view = 'filament.pages.annual-dues';

    protected static ?string $navigationLabel = 'Annual Dues';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    public ?int $homeownerId = null;

    public int $year;

    public static function canAccess(): bool
    {
        return OperationalAccess::allows(app(AuthenticatedActor::class)->user(), 'manage_payments');
    }

    public function mount(): void
    {
        $this->year = now('Asia/Manila')->year;
    }

    protected function resident(): ?Homeowner
    {
        abort_unless(static::canAccess(), 403);

        return $this->homeownerId ? Homeowner::findOrFail($this->homeownerId) : null;
    }

    protected function getViewData(): array
    {
        $this->validate(['year' => ['required', 'integer', 'between:1900,'.now('Asia/Manila')->year]]);
        $resident = $this->resident();

        return [
            'residents' => $this instanceof \App\Filament\Homeowner\Pages\AnnualDues ? collect() : Homeowner::with('user')->orderBy('id')->get(),
            'summary' => $resident ? app(AnnualDuesSummary::class)->forResident($resident, $this->year) : null,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('prepareBillingMonth')->label(new HtmlString('<span data-i18n="Prepare billing month">'.e(__('Prepare billing month')).'</span>'))->schema([
            Select::make('homeowner_id')->label(__('Homeowner'))->options(fn () => Homeowner::with('user')->get()->mapWithKeys(fn ($owner) => [$owner->id => $owner->user->full_name.' · '.$owner->full_address]))->searchable()->required(),
            Select::make('dues_setting_id')->label(__('Monthly dues schedule'))->options(fn () => DuesSetting::where('frequency', 'Monthly')->where('is_active', true)->pluck('name', 'id'))->required(),
            Select::make('billing_year')->label(__('Year'))->options(array_combine(range(now('Asia/Manila')->year - 10, now('Asia/Manila')->year), range(now('Asia/Manila')->year - 10, now('Asia/Manila')->year)))->default(now('Asia/Manila')->year)->required(),
            Select::make('billing_month')->label(__('Billing month'))->options(collect(range(1, 12))->mapWithKeys(fn ($month) => [$month => __(date('F', mktime(0, 0, 0, $month, 1)))])->all())->required(),
        ])->action(function (array $data): void {
            abort_unless(static::canAccess(), 403);
            $record = app(PrepareMonthlyAssessment::class)->handle($data, app(AuthenticatedActor::class)->user());
            $this->homeownerId = (int) $data['homeowner_id'];
            $this->year = (int) $data['billing_year'];
            Notification::make()->info()->title(__('Billing Month Available for Payment'))->body(__('Select this billing month when recording the payment.'))->send();
        })];
    }
}
