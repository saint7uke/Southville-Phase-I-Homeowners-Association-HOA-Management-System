<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Widgets;

use App\Models\Announcement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class LatestAnnouncements extends TableWidget
{
    protected static ?string $heading = 'Latest announcements';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table->query(Announcement::query()->visibleToResidents()->latest('published_at')->limit(3))->columns([
            TextColumn::make('title')->wrap(),
            TextColumn::make('category')->badge(),
            TextColumn::make('published_at')->since(),
        ])->paginated(false);
    }
}
