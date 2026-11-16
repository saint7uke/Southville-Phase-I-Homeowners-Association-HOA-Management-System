<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ActionConfirmation
{
    public static function attributes(Action $action): array
    {
        if (! $action->canSubmitForm() || ! $action->isConfirmationRequired()) {
            return [];
        }

        return ['data-hoa-submit-confirm' => e(json_encode(self::content($action), JSON_THROW_ON_ERROR))];
    }

    public static function content(Action $action): array
    {
        $name = $action->getName();
        $verb = match (true) {
            str_contains(strtolower($name), 'restore') => 'Yes, Restore',
            str_contains(strtolower($name), 'delete') => 'Yes, Delete',
            str_contains(strtolower($name), 'approve') => 'Yes, Approve',
            str_contains(strtolower($name), 'reject') => 'Yes, Reject',
            default => 'Confirm',
        };
        $description = $action->getModalDescription();
        $record = $action->getRecord();
        if (str_contains(strtolower($name), 'delete') && (blank($description) || $description === __('filament-actions::modal.confirmation'))) {
            $model = $record ? $record::class : $action->getModel();
            $softDeletes = $model && in_array(SoftDeletes::class, class_uses_recursive($model), true);
            $description = __($softDeletes ? 'This record will be archived and can be restored.' : 'This record will be permanently deleted. This cannot be undone.');
        }

        return ['title' => strip_tags((string) $action->getModalHeading()),
            'text' => strip_tags((string) $description), 'confirmButtonText' => __($verb)];
    }
}
