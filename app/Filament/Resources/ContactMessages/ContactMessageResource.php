<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\EditContactMessage;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Inquiry'))->schema([
                TextEntry::make('reference')->label(__('Inquiry reference'))->icon('heroicon-o-hashtag')->copyable(),
                TextEntry::make('status')->icon('heroicon-o-flag')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->badge()->color(fn ($state): string => self::statusColor((string) $state)),
                TextEntry::make('created_at')->label(__('Received'))->icon('heroicon-o-clock')->dateTime('M d, Y g:i A'),
                TextEntry::make('name')->label(__('Sender name'))->icon('heroicon-o-user'),
                TextEntry::make('email')->icon('heroicon-o-envelope'),
                TextEntry::make('phone')->icon('heroicon-o-phone')->placeholder(__('Not provided')),
                TextEntry::make('subject')->icon('heroicon-o-chat-bubble-left-ellipsis')->columnSpanFull(),
                TextEntry::make('message')->icon('heroicon-o-document-text')->columnSpanFull(),
            ])->description(__('Public inquiry details and sender contact information.'))->columns(['md' => 2, 'xl' => 3])->columnSpanFull(),
            Section::make(__('Private activity timeline'))->schema([
                Text::make(fn (?ContactMessage $record) => $record === null
                    ? ''
                    : new HtmlString(view('filament.resources.contact-messages.activity-timeline', [
                        'activities' => $record->activities()->with('author')->get(),
                    ])->render())),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable()->copyable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('subject')->searchable()->wrap(),
            TextColumn::make('status')->formatStateUsing(fn ($state) => $state === null ? null : __((string) $state))->badge()->color(fn ($state): string => self::statusColor((string) $state)),
            TextColumn::make('activities_count')->counts('activities')->label(__('Activity'))->badge(),
            TextColumn::make('handler.full_name')->label(__('Last handled by'))->placeholder(__('Unassigned'))->toggleable(),
            TextColumn::make('handled_at')->label(__('Last handled'))->dateTime()->sortable()->toggleable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc')->filters([
            SelectFilter::make('status')->options(array_combine(ContactMessage::STATUSES, array_map('__', ContactMessage::STATUSES))),
            Filter::make('received')->schema([
                DatePicker::make('from')->label(__('Received from')),
                DatePicker::make('to')->label(__('Received to'))->afterOrEqual('from'),
            ])->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                ->when($data['to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            TrashedFilter::make()->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
        ])->recordActions([EditAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['handler'])->withCount('activities');

        return (Filament::auth()->user()?->hasRole('hoa_admin') ?? false)
            ? $query->withoutGlobalScopes([SoftDeletingScope::class])
            : $query;
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/'), 'edit' => EditContactMessage::route('/{record}/edit')];
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'New' => 'info',
            'In Progress' => 'warning',
            'Awaiting Resident' => 'primary',
            'Closed' => 'success',
            default => 'gray',
        };
    }
}
