<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequests\Schemas;

use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class ServiceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('homeowner_id')->relationship('homeowner', 'id')->getOptionLabelFromRecordUsing(fn (Homeowner $record): string => $record->user->full_name)->searchable()->preload()->required(),
            Select::make('request_type')->options(array_combine(ServiceRequest::TYPES, ServiceRequest::TYPES))->required(),
            TextInput::make('subject')->required()->maxLength(160),
            Textarea::make('details')->maxLength(3000)->columnSpanFull(),
            Select::make('status')->options(['Pending' => 'Pending', 'Processing' => 'Processing', 'Approved' => 'Approved', 'Completed' => 'Completed', 'Rejected' => 'Rejected'])->required()->live(),
            Select::make('handled_by')->label('Assigned staff')->relationship('handler', 'email', modifyQueryUsing: fn (Builder $query): Builder => $query->role('hoa_staff')->where('account_status', 'Active'))->getOptionLabelFromRecordUsing(fn (User $record): string => $record->full_name.' | '.$record->email)->searchable()->preload(),
            Textarea::make('admin_remarks')->maxLength(3000)->required(fn (callable $get): bool => $get('status') === 'Rejected')->columnSpanFull(),
        ])->columns(2);
    }
}
