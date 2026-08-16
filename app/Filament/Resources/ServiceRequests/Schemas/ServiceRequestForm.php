<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequests\Schemas;

use App\Models\Homeowner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

final class ServiceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('homeowner_id')->relationship('homeowner', 'id')->getOptionLabelFromRecordUsing(fn (Homeowner $record): string => $record->user->full_name)->searchable()->preload()->required(),
            Select::make('request_type')->options(array_combine($types = ['Certificate of Residency', 'HOA Clearance', 'Gate Pass', 'Facility Reservation', 'Other'], $types))->required(),
            Textarea::make('details')->maxLength(3000)->columnSpanFull(),
            Select::make('status')->options(['Pending' => 'Pending', 'Processing' => 'Processing', 'Completed' => 'Completed', 'Rejected' => 'Rejected'])->required(),
            Textarea::make('admin_remarks')->maxLength(3000)->columnSpanFull(),
        ])->columns(2);
    }
}
