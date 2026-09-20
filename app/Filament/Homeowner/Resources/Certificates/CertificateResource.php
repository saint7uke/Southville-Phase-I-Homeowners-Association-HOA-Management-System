<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Certificates;

use App\Filament\Homeowner\Resources\Certificates\Pages\ListCertificates;
use App\Models\Certificate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

final class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('certificate_number')->label('Certificate no.')->searchable(),
            TextColumn::make('type')->wrap(),
            TextColumn::make('status')->badge(),
            TextColumn::make('issued_at')->dateTime()->sortable(),
            TextColumn::make('expires_at')->dateTime()->placeholder('No expiry'),
        ])->defaultSort('issued_at', 'desc')->recordActions([
            Action::make('download')->url(fn (Certificate $record): string => URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $record))->openUrlInNewTab()->visible(fn (Certificate $record): bool => $record->status === 'Issued'),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('homeowner', fn (Builder $query): Builder => $query->where('user_id', Filament::auth()->id()));
    }

    public static function getPages(): array
    {
        return ['index' => ListCertificates::route('/')];
    }
}
