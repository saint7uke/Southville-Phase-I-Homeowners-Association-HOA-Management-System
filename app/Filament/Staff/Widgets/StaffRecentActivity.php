<?php

declare(strict_types=1);

namespace App\Filament\Staff\Widgets;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ContactMessage;
use App\Models\Payment;
use App\Models\ServiceRequest;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class StaffRecentActivity extends TableWidget
{
    protected static ?string $heading = 'Recent operational activity';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table->query(AuditLog::query()->with('user')->where('user_id', Filament::auth()->id())->whereIn('auditable_type', [Complaint::class, ServiceRequest::class, Payment::class, ContactMessage::class])->latest('id')->limit(10))->columns([
            TextColumn::make('user.full_name')->label('Actor')->placeholder('System'),
            TextColumn::make('action')->badge(),
            TextColumn::make('auditable_type')->label('Record')->formatStateUsing(fn (?string $state): string => class_basename((string) $state)),
            TextColumn::make('created_at')->since(),
        ])->paginated(false);
    }
}
