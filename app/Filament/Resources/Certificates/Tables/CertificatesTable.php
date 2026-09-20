<?php

declare(strict_types=1);

namespace App\Filament\Resources\Certificates\Tables;

use App\Models\Certificate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

final class CertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('certificate_number')->label('Certificate no.')->searchable(),
            TextColumn::make('homeowner.user.full_name')->label('Resident')->searchable(['first_name', 'last_name']),
            TextColumn::make('type')->wrap(),
            TextColumn::make('status')->badge(),
            TextColumn::make('issued_at')->dateTime()->sortable(),
            TextColumn::make('expires_at')->dateTime()->placeholder('No expiry'),
        ])->defaultSort('issued_at', 'desc')->filters([
            TrashedFilter::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
        ])->recordActions([
            Action::make('download')
                ->url(fn (Certificate $record): string => URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $record))
                ->openUrlInNewTab()
                ->visible(fn (Certificate $record): bool => $record->status === 'Issued'),
            EditAction::make(),
            DeleteAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
            RestoreAction::make()->visible(fn (): bool => Filament::getCurrentPanel()?->getId() === 'admin'),
        ]);
    }
}
