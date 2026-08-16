<?php

declare(strict_types=1);

namespace App\Filament\Resources\Complaints\Schemas;

use App\Models\Homeowner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class ComplaintForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('homeowner_id')->relationship('homeowner', 'id')->getOptionLabelFromRecordUsing(fn (Homeowner $record): string => $record->user->full_name)->searchable()->preload()->required(),
            TextInput::make('subject')->maxLength(160)->required(),
            Select::make('category')->options(array_combine($values = ['Noise', 'Property Damage', 'Neighbor Dispute', 'Common Area', 'Security', 'Others'], $values))->required(),
            Select::make('priority')->options(['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High'])->required(),
            Textarea::make('description')->rows(6)->maxLength(5000)->required()->columnSpanFull(),
            Select::make('status')->options(['Pending' => 'Pending', 'Under Review' => 'Under Review', 'Resolved' => 'Resolved', 'Dismissed' => 'Dismissed'])->required(),
            Textarea::make('admin_remarks')->rows(4)->maxLength(3000)->columnSpanFull(),
        ])->columns(2);
    }
}
