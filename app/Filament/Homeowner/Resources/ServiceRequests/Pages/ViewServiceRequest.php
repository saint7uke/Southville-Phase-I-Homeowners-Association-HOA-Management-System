<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\ServiceRequests\Pages;

use App\Filament\Homeowner\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\URL;

final class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        $actions = $this->record->caseAttachments()->get()->map(fn ($attachment): Action => Action::make('attachment_'.$attachment->id)->label($attachment->original_name)->icon('heroicon-o-paper-clip')->url(route('case-attachments.download', $attachment))->openUrlInNewTab())->all();
        $certificate = $this->record->certificate()->first();

        if ($certificate?->status === 'Issued') {
            $actions[] = Action::make('download_certificate')
                ->label('Download certificate')
                ->icon('heroicon-o-document-arrow-down')
                ->url(URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $certificate))
                ->openUrlInNewTab();
        }

        return $actions;
    }
}
