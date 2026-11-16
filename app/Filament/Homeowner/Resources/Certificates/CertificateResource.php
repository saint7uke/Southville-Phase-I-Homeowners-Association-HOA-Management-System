<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Certificates;

use App\Filament\Homeowner\Resources\Certificates\Pages\ListCertificates;
use App\Models\Certificate;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'My Home';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('certificate_number')->label(__('Certificate no.'))->searchable(),
            TextColumn::make('type')->wrap(),
            TextColumn::make('status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->badge(),
            TextColumn::make('issued_at')->dateTime()->sortable(),
            TextColumn::make('expires_at')->dateTime()->placeholder(__('No expiry')),
        ])->defaultSort('issued_at', 'desc')->recordActions([
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
