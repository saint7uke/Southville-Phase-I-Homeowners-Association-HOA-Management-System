<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Dues;

use App\Actions\Payments\SubmitPaymentProof;
use App\Filament\Homeowner\Resources\Dues\Pages\ListDuesObligations;
use App\Models\DuesObligation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

final class DuesObligationResource extends Resource
{
    protected static ?string $model = DuesObligation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Outstanding Dues';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('duesSetting.name')->label('Dues type'),
            TextColumn::make('billing_period')->label('Period')->state(fn (DuesObligation $record): string => sprintf('%04d-%02d', $record->billing_year, $record->billing_month)),
            TextColumn::make('amount_due')->money('PHP')->sortable(),
            TextColumn::make('penalty_amount')->money('PHP')->sortable(),
            TextColumn::make('amount_paid')->money('PHP')->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('due_date')->date()->sortable(),
        ])->defaultSort('due_date')->recordActions([
            Action::make('submitProof')
                ->label('Submit payment proof')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    TextInput::make('amount_paid')->label('Amount paid')->numeric()->prefix('PHP')->required()->minValue(0.01),
                    DatePicker::make('payment_date')->required()->maxDate(today())->default(today()),
                    Select::make('payment_method')->options(['Bank Transfer' => 'Bank Transfer', 'GCash' => 'GCash', 'Maya' => 'Maya'])->required(),
                    Textarea::make('notes')->maxLength(500),
                    FileUpload::make('proof')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120)->storeFiles(false)->required(),
                ])
                ->visible(fn (DuesObligation $record): bool => in_array($record->status, ['Pending', 'Partial', 'Overdue'], true))
                ->action(function (DuesObligation $record, array $data): void {
                    $actor = Filament::auth()->user();
                    $proof = $data['proof'] ?? null;
                    abort_unless($actor instanceof User && $proof instanceof UploadedFile, 422);
                    app(SubmitPaymentProof::class)->handle($actor, $record, [
                        'amount_paid' => (string) $data['amount_paid'],
                        'payment_date' => $data['payment_date'],
                        'covered_period' => sprintf('%04d-%02d', $record->billing_year, $record->billing_month),
                        'payment_method' => $data['payment_method'],
                        'notes' => $data['notes'] ?? null,
                    ], $proof);
                    Notification::make()->success()->title('Proof submitted for staff review')->send();
                }),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('homeowner', fn (Builder $query): Builder => $query->where('user_id', Filament::auth()->id()));
    }

    public static function getPages(): array
    {
        return ['index' => ListDuesObligations::route('/')];
    }
}
