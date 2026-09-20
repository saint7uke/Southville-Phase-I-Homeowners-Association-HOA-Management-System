<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Complaints\Pages;

use App\Filament\Homeowner\Resources\Complaints\ComplaintResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewComplaint extends ViewRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return $this->record->caseAttachments()->get()->map(fn ($attachment): Action => Action::make('attachment_'.$attachment->id)->label($attachment->original_name)->icon('heroicon-o-paper-clip')->url(route('case-attachments.download', $attachment))->openUrlInNewTab())->all();
    }
}
