<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\UserTablePreference;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function loadTableColumnsFromSession(): array
    {
        $stored = $this->preferenceQuery()->first()?->columns ?? [];

        return $this->reconcileColumns($stored);
    }

    protected function setTableColumns(array $tableColumns): void
    {
        parent::setTableColumns($this->reconcileColumns($tableColumns));
    }

    protected function persistTableColumns(): void
    {
        $this->preferenceQuery()->updateOrCreate([
            'user_id' => Filament::auth()->id(), 'panel' => 'admin', 'table_key' => 'users',
        ], ['columns' => $this->reconcileColumns($this->tableColumns)]);
    }

    public function resetTableColumnManager(): void
    {
        parent::resetTableColumnManager();
        $this->preferenceQuery()->delete();
    }

    private function preferenceQuery(): Builder
    {
        abort_unless(Filament::auth()->user()?->hasRole('hoa_admin'), 403);

        return UserTablePreference::query()->where([
            'user_id' => Filament::auth()->id(),
            'panel' => 'admin', 'table_key' => 'users',
        ]);
    }

    private function reconcileColumns(array $state): array
    {
        $byName = collect($state)->filter(fn ($item): bool => is_array($item) && is_string($item['name'] ?? null))->keyBy('name');

        return array_map(function (array $column) use ($byName): array {
            $saved = $byName->get($column['name']);
            if ($column['isToggleable'] && is_bool($saved['isToggled'] ?? null)) {
                $column['isToggled'] = $saved['isToggled'];
            }
            if (! $column['isToggleable']) {
                $column['isToggled'] = true;
            }

            return $column;
        }, $this->getDefaultTableColumnState());
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
