<?php

declare(strict_types=1);

namespace App\Filament\Resources\Complaints\Schemas;

use App\Models\Homeowner;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class ComplaintForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('homeowner_id')->relationship('homeowner', 'id')->getOptionLabelFromRecordUsing(fn (Homeowner $record): string => $record->user->full_name)->searchable()->preload()->required(),
            TextInput::make('subject')->maxLength(160)->required(),
            Select::make('category')->options(array_combine($values = ['Noise', 'Property Damage', 'Neighbor Dispute', 'Common Area', 'Security', 'Others'], $values))->required(),
            Select::make('priority')->options(['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High', 'Urgent' => 'Urgent'])->required(),
            Textarea::make('description')->rows(6)->maxLength(5000)->required()->columnSpanFull(),
            Select::make('status')->options(['Pending' => 'Pending', 'Under Review' => 'Under Review', 'Resolved' => 'Resolved', 'Rejected' => 'Rejected', 'Closed' => 'Closed', 'Dismissed' => 'Dismissed'])->required()->live(),
            Select::make('handled_by')->label('Assigned staff')->relationship('handler', 'email', modifyQueryUsing: fn (Builder $query): Builder => $query->role('hoa_staff')->where('account_status', 'Active'))->getOptionLabelFromRecordUsing(fn (User $record): string => $record->full_name.' | '.$record->email)->searchable()->preload(),
            Textarea::make('admin_remarks')->label('Resolution notes')->rows(4)->maxLength(3000)->required(fn (callable $get): bool => in_array($get('status'), ['Resolved', 'Rejected', 'Closed', 'Dismissed'], true))->columnSpanFull(),
        ])->columns(2);
    }
}
