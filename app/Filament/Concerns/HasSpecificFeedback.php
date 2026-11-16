<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Announcement;
use App\Models\ServiceRequest;
use App\Support\ActionFeedback;

trait HasSpecificFeedback
{
    protected ?string $feedbackPreviousStatus = null;

    protected bool $feedbackPreviouslyPublished = false;

    protected function beforeSave(): void
    {
        $this->feedbackPreviousStatus = $this->getRecord()->status;
        $this->feedbackPreviouslyPublished = $this->getRecord() instanceof Announcement
            && Announcement::query()->published()->whereKey($this->getRecord()->getKey())->exists();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return ActionFeedback::title(static::getModel(), 'create');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        $record = $this->getRecord();
        $operation = 'update';
        if ($record instanceof Announcement && ! $this->feedbackPreviouslyPublished
            && Announcement::query()->published()->whereKey($record->getKey())->exists()) {
            $operation = 'publish';
        } elseif ($record instanceof ServiceRequest && $record->status !== $this->feedbackPreviousStatus) {
            $operation = match ($record->status) {
                'Approved' => 'approve', 'Rejected' => 'reject', default => 'update',
            };
        }

        return ActionFeedback::title(static::getModel(), $operation);
    }
}
