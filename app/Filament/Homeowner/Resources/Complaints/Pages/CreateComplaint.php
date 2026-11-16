<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Resources\Complaints\Pages;

use App\Actions\Complaints\CreateComplaint as CreateComplaintAction;
use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Homeowner\Resources\Complaints\ComplaintResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateComplaint extends CreateRecord
{
    use HasSpecificFeedback;

    protected static string $resource = ComplaintResource::class;

    protected static bool $canCreateAnother = false;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->requiresConfirmation()
            ->modalHeading(__('Review and submit complaint'))
            ->modalDescription(__('Please review every field and attachment before confirming. After submission, the complaint enters the HOA review workflow.'));
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = Filament::auth()->user();
        abort_unless($actor instanceof User, 403);
        $homeowner = $actor->homeowner()->firstOrFail();
        $attachments = $data['attachments'] ?? [];
        unset($data['attachments']);
        $data['subject'] = ($data['category'] ?? '') === 'Others' ? trim($data['category_other'] ?? '') : ($data['category'] ?? '');
        $data['priority'] = 'Medium';

        return app(CreateComplaintAction::class)->handle($homeowner, $data, $attachments, $actor);
    }
}
