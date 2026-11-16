<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\MapsDomainValidation;
use App\Models\SmsDelivery;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Sms\SmsGatewayException;
use App\Services\Sms\SmsManagement;
use App\Services\Sms\TextBeeClient;
use App\Support\AuthenticatedActor;
use App\Support\PhilippineMobile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

final class SmsNotifications extends Page implements HasTable
{
    private static function typeLabels(): array
    {
        return array_map(__(...), ['approval' => 'Registration approval', 'due' => 'Monthly dues reminder', 'overdue' => 'Overdue dues reminder', 'payment' => 'Payment confirmation', 'complaint' => 'Complaint status update', 'announcement' => 'Important announcement', 'otp' => 'Mobile number verification', 'test' => 'Test SMS', 'incoming' => 'Incoming SMS']);
    }

    use InteractsWithTable;
    use MapsDomainValidation;

    protected string $view = 'filament.pages.sms-notifications';

    protected static ?string $navigationLabel = 'SMS Notifications';

    protected static string|\UnitEnum|null $navigationGroup = 'Communication';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public ?array $deviceHealth = null;

    public static function canAccess(): bool
    {
        $user = app(AuthenticatedActor::class)->user();

        return $user && ! $user->trashed() && $user->account_status === 'Active' && $user->hasRole('hoa_admin') && $user->can('manage_settings');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    public function statistics(): array
    {
        abort_unless(self::canAccess(), 403);

        return ['attempts' => (int) SmsDelivery::where('direction', 'outgoing')->sum('attempts'),
            'statuses' => SmsDelivery::where('direction', 'outgoing')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'types' => SmsDelivery::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->all(),
            'segments' => (int) DB::table('sms_daily_usage')->where('day', now('Asia/Manila')->toDateString())->value('segments')];
    }

    public function table(Table $table): Table
    {
        abort_unless(self::canAccess(), 403);

        return $table->query(SmsDelivery::query()->when(! SystemSetting::current()->sms_incoming_enabled, fn ($query) => $query->where('direction', 'outgoing')))
            ->defaultSort('id', 'desc')->columns([
                TextColumn::make('recipient')->label(__('Recipient'))->state(fn ($record): string => User::whereIn('id', $record->user_ids)->get()->pluck('full_name')->join(', ') ?: __('Test, verification or incoming SMS')),
                TextColumn::make('masked_number')->label(__('Phone number'))->state(fn ($record): string => PhilippineMobile::mask($record->phone))
                    ->searchable(query: function ($query, string $search) {
                        $number = PhilippineMobile::normalize($search);
                        $ids = User::where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')->limit(100)->pluck('id');

                        return $query->where(function ($query) use ($number, $ids): void {
                            $query->where('phone_hash', $number ? PhilippineMobile::fingerprint($number) : 'unmatchable');
                            foreach ($ids as $id) {
                                $query->orWhereJsonContains('user_ids', (int) $id);
                            }
                        });
                    }),
                TextColumn::make('type')->label(__('Notification type'))->formatStateUsing(fn ($state) => self::typeLabels()[$state] ?? __($state)),
                TextColumn::make('preview')->label(__('Message preview'))->state(fn ($record): string => $record->type === 'otp' ? __('Verification code hidden') : ($record->body ?? __('Content expired')))->limit(120)->wrap(),
                TextColumn::make('created_at')->label(__('Date and time'))->dateTime()->timezone('Asia/Manila'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => __($state)),
                TextColumn::make('failure_code')->label(__('Failure category'))->placeholder('—')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([
                SelectFilter::make('type')->label(__('Notification type'))->options(fn () => self::typeLabels()),
                SelectFilter::make('status')->options(collect(['Queued', 'Accepted', 'Pending', 'Dispatched', 'Sent', 'Delivered', 'Failed', 'Uncertain', 'Unknown', 'Skipped', 'Expired', 'Received'])->mapWithKeys(fn ($value) => [$value => __($value)])->all()),
                Filter::make('dates')->schema([DatePicker::make('from'), DatePicker::make('to')->afterOrEqual('from')])->query(fn ($query, $data) => $query
                    ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($data['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $toggles = [];
        foreach (['enabled' => 'Enable SMS notifications', 'approval_enabled' => 'Registration approval SMS', 'due_enabled' => 'Monthly dues SMS',
            'overdue_enabled' => 'Overdue reminder SMS', 'payment_enabled' => 'Payment confirmation SMS', 'complaint_enabled' => 'Complaint status SMS',
            'announcement_enabled' => 'Important announcement SMS', 'incoming_enabled' => 'Enable incoming HOA SMS inbox'] as $key => $label) {
            $toggles[] = Toggle::make('sms_'.$key)->label(__($label))->required();
        }

        return [
            Action::make('settings')->label(__('SMS Settings'))->fillForm(fn () => SystemSetting::current()->fresh()->getAttributes())->schema([
                ...$toggles, TextInput::make('sms_due_days')->label(__('Days before dues deadline'))->numeric()->required()->minValue(0)->maxValue(31),
                TextInput::make('sms_overdue_days')->label(__('Days between overdue reminders; 0 means once'))->numeric()->required()->minValue(0)->maxValue(365),
                TextInput::make('sms_daily_segments')->label(__('Daily SMS segment cap'))->numeric()->required()->minValue(1)->maxValue(10000),
            ])->action(function (array $data): void {
                $this->runValidatedDomainAction(fn () => app(SmsManagement::class)->settings(app(AuthenticatedActor::class)->user(), $data));
                Notification::make()->success()->title(__('SMS Settings Updated Successfully!'))->send();
            }),
            Action::make('testSms')->label(__('Send Test SMS'))->schema([TextInput::make('number')->label(__('Test mobile number'))->tel()->required()->maxLength(11)->regex(PhilippineMobile::INPUT_REGEX)->extraInputAttributes(['inputmode' => 'numeric', 'data-local-mobile' => '', 'pattern' => '09[0-9]{9}'])->helperText(__('Enter an 11-digit Philippine mobile number beginning with 09.'))])
                ->modalDescription(__('Send one fixed test message to this number? SIM charges and SMS safety limits apply.'))
                ->modalSubmitActionLabel(__('Confirm test SMS'))->action(function (array $data): void {
                    $batch = $this->runValidatedDomainAction(fn () => app(SmsManagement::class)->test(app(AuthenticatedActor::class)->user(), $data['number']));
                    Notification::make()->status($batch->status === 'Accepted' ? 'success' : 'warning')
                        ->title(__($batch->status === 'Accepted' ? 'SMS Submitted to Gateway Successfully!' : 'Test SMS Was Not Confirmed by the Gateway'))
                        ->body(__('Gateway acceptance does not confirm handset delivery.').' '.($batch->failure_code ?? ''))->send();
                }),
            Action::make('deviceHealth')->label(__('Check SMS Device'))->action(function (): void {
                app(SmsManagement::class)->authorize(app(AuthenticatedActor::class)->user());
                try {
                    $device = app(TextBeeClient::class)->device();
                    $this->deviceHealth = array_intersect_key($device, array_flip(['enabled', 'lastHeartbeat', 'heartbeatIntervalMinutes', 'receiveSMSEnabled', 'smsSendDelaySeconds']));
                    Notification::make()->info()->title(__('SMS Device Status Retrieved'))->send();
                } catch (SmsGatewayException) {
                    Notification::make()->danger()->title(__('SMS Device Status Could Not Be Retrieved'))->send();
                }
            }),
        ];
    }
}
