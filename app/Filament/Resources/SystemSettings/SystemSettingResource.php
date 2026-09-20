<?php

declare(strict_types=1);

namespace App\Filament\Resources\SystemSettings;

use App\Filament\Resources\SystemSettings\Pages\EditSystemSetting;
use App\Filament\Resources\SystemSettings\Pages\ListSystemSettings;
use App\Models\SystemSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'System Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('HOA identity')->schema([
                TextInput::make('hoa_name')->required()->maxLength(160),
                TextInput::make('contact_email')->email()->maxLength(255),
                TextInput::make('address')->required()->maxLength(500)->columnSpanFull(),
                FileUpload::make('logo_path')
                    ->label('HOA logo')
                    ->disk('local')
                    ->directory('branding')
                    ->visibility('private')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => (string) str()->ulid().'.'.$file->guessExtension())
                    ->downloadable()
                    ->columnSpanFull(),
                TextInput::make('delinquency_months')->label('Consecutive overdue months before delinquency')->integer()->minValue(1)->maxValue(12)->required(),
            ])->columns(2),
            Section::make('Email notifications')->schema([
                Toggle::make('complaint_notifications'),
                Toggle::make('request_notifications'),
                Toggle::make('announcement_notifications'),
                Toggle::make('certificate_notifications'),
                Toggle::make('delinquency_notifications'),
                Toggle::make('contact_notifications'),
            ])->columns(2),
            Section::make('Panel availability')->description('Admin access always remains available.')->schema([
                Toggle::make('staff_panel_enabled'),
                Toggle::make('homeowner_panel_enabled'),
            ])->columns(2),
            Section::make('SMTP configuration')->description('Credentials remain environment-managed and are never stored in the database.')->schema([
                TextInput::make('smtp_host')->label('SMTP host')->default((string) config('mail.mailers.smtp.host'))->disabled()->dehydrated(false),
                TextInput::make('smtp_from')->label('From address')->default((string) config('mail.from.address'))->disabled()->dehydrated(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('hoa_name')->label('HOA'),
            TextColumn::make('delinquency_months')->label('Delinquency threshold')->suffix(' months'),
            IconColumn::make('staff_panel_enabled')->boolean(),
            IconColumn::make('homeowner_panel_enabled')->boolean(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSystemSettings::route('/'), 'edit' => EditSystemSetting::route('/{record}/edit')];
    }
}
