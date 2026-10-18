<?php

declare(strict_types=1);

namespace App\Filament\Resources\Complaints\Pages;

use App\Filament\Resources\Complaints\Actions\ReopenComplaintAction;
use App\Filament\Resources\Complaints\ComplaintResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewComplaint extends ViewRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [ReopenComplaintAction::make()];

        foreach ($this->record->caseAttachments()->get() as $attachment) {
            $actions[] = Action::make('attachment_'.$attachment->id)
                ->label($attachment->original_name)
                ->icon('heroicon-o-paper-clip')
                ->url(route('case-attachments.download', $attachment))
                ->openUrlInNewTab();
        }

        return $actions;
    }
}
