<?php

declare(strict_types=1);

namespace App\Filament\Resources\Homeowners\Tables;

use App\Actions\Users\ChangeAccountStatus;
use App\Enums\UserAccountStatus;
use App\Models\AuditLog;
use App\Models\Homeowner;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HomeownersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.full_name')->label('Resident')->searchable(['first_name', 'last_name'])->sortable(['last_name']),
            TextColumn::make('full_address')->label('Property address')->searchable(['house_number', 'street', 'block', 'lot'])->wrap(),
            TextColumn::make('user.email')->label('Email')->searchable()->toggleable(),
            TextColumn::make('ownership_type')->badge(),
            TextColumn::make('status')->badge(),
            TextColumn::make('residency_date')->date()->sortable(),
            TextColumn::make('user.contact_number')->label('Contact'),
        ])->defaultSort('created_at', 'desc')->filters([
            SelectFilter::make('status')->options(['Active' => 'Active', 'Inactive' => 'Inactive', 'Delinquent' => 'Delinquent']),
            SelectFilter::make('ownership_type')->options(['Owner' => 'Owner', 'Tenant' => 'Tenant', 'Co-owner' => 'Co-owner']),
            Filter::make('residency_date')->schema([
                DatePicker::make('from')->label('Move-in from'),
                DatePicker::make('to')->label('Move-in to')->afterOrEqual('from'),
            ])->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('residency_date', '>=', $date))
                ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('residency_date', '<=', $date))),
            TrashedFilter::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ])->recordActions([
            ViewAction::make(),
            EditAction::make(),
            Action::make('deactivate')
                ->label('Deactivate')
                ->requiresConfirmation()
                ->color('warning')
                ->visible(fn (Homeowner $record): bool => Filament::getCurrentPanel()?->getId() === 'admin' && $record->user?->account_status === UserAccountStatus::Active->value)
                ->action(fn (Homeowner $record) => app(ChangeAccountStatus::class)->handle($record->user, UserAccountStatus::Inactive, Filament::auth()->user())),
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ])->toolbarActions([
            BulkActionGroup::make([
                BulkAction::make('exportSelected')
                    ->label('Export selected CSV')
                    ->action(function (Collection $records): StreamedResponse {
                        $records->each(fn (Homeowner $record) => Gate::authorize('view', $record));
                        AuditLog::query()->create([
                            'user_id' => Filament::auth()->id(),
                            'panel' => 'admin',
                            'action' => 'Homeowner.selected_exported',
                            'new_values' => ['count' => $records->count(), 'ids' => $records->pluck('id')->values()->all()],
                            'ip_address' => request()->ip(),
                            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                        ]);

                        return response()->streamDownload(function () use ($records): void {
                            $handle = fopen('php://output', 'wb');
                            fputcsv($handle, ['Homeowner', 'Email', 'Contact', 'Address', 'Ownership', 'Move-in Date', 'Status']);
                            $safe = static fn (mixed $value): string => preg_match('/^[=+\-@]/', (string) $value) ? "'".(string) $value : (string) $value;
                            foreach ($records->load('user') as $record) {
                                fputcsv($handle, array_map($safe, [
                                    $record->user->full_name,
                                    $record->user->email,
                                    $record->user->contact_number,
                                    $record->full_address,
                                    $record->ownership_type,
                                    $record->residency_date?->toDateString(),
                                    $record->status,
                                ]));
                            }
                            fclose($handle);
                        }, 'homeowners-selected-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
                BulkAction::make('deactivateSelected')
                    ->label('Deactivate selected')
                    ->requiresConfirmation()
                    ->color('warning')
                    ->action(function (Collection $records): void {
                        $actor = Filament::auth()->user();
                        foreach ($records->load('user') as $record) {
                            Gate::forUser($actor)->authorize('update', $record);
                            if ($record->user->account_status === UserAccountStatus::Active->value) {
                                app(ChangeAccountStatus::class)->handle($record->user, UserAccountStatus::Inactive, $actor);
                            }
                        }
                    }),
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
            ])->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ]);
    }
}
