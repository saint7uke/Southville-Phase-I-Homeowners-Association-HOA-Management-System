<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\ServiceRequests\Pages;

use App\Filament\Concerns\HasIconOnlyRecordActions;
use App\Filament\Homeowner\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewServiceRequest extends ViewRecord
{
    use HasIconOnlyRecordActions;

    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        $actions = $this->record->caseAttachments()->get()->map(fn ($attachment): Action => Action::make('attachment_'.$attachment->id)->label($attachment->original_name)->icon('heroicon-o-paper-clip')->url(route('case-attachments.download', $attachment))->openUrlInNewTab())->all();

        return $actions;
    }
}
