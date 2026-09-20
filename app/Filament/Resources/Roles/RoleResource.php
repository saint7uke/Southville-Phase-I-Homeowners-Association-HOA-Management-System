<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Role;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

final class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('name')->label('Built-in role')->content(fn (Role $record): string => $record->name),
            CheckboxList::make('permission_names')->label('Permissions')->options(fn (): array => Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name', 'name')->all())->columns(2)->bulkToggleable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->badge()->searchable(),
            TextColumn::make('permissions_count')->counts('permissions')->label('Permissions')->sortable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRoles::route('/'), 'edit' => EditRole::route('/{record}/edit')];
    }
}
