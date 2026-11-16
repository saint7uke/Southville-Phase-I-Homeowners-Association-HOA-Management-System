<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Presentation only: never change action handlers, visibility or confirmation. */
final class RecordActionIcons
{
    public static function action(Action $action): Action
    {
        // Bulk actions are menu entries, not individual record controls.
        if ($action instanceof BulkAction) {
            return $action;
        }

        if (! $action->hasIcon() || str_contains(strtolower($action->getName()), 'delete')) {
            $action->icon(fn (Action $action): string => self::icon($action));
        }

        if (! $action->hasTooltip()) {
            $action->tooltip(fn (Action $action): string => self::label($action));
        }

        if (! $action->hasColor()) {
            $action->color(self::color($action));
        }

        return $action->button()
            ->extraAttributes(fn (Action $action): array => [
                'data-hoa-record-action' => 'true',
                'data-hoa-record-tooltip' => e(trim(strip_tags((string) $action->getTooltip()))),
                'class' => 'hoa-system-action-button',
            ], merge: true);
    }

    public static function group(ActionGroup $group): ActionGroup
    {
        return $group->icon('heroicon-o-ellipsis-horizontal')->button()
            ->tooltip(fn (ActionGroup $group): string => self::label($group))
            ->extraAttributes(['data-hoa-record-action' => 'true', 'class' => 'hoa-system-action-button'], merge: true);
    }

    private static function color(Action $action): string
    {
        $name = strtolower($action->getName());

        return match (true) {
            str_contains($name, 'delete'), str_contains($name, 'archive'), str_contains($name, 'reject'),
            str_contains($name, 'deactivate'), str_contains($name, 'revoke'), str_contains($name, 'remove'),
            str_contains($name, 'clear') => 'danger',
            str_contains($name, 'create'), str_contains($name, 'save'), str_contains($name, 'submit'),
            str_contains($name, 'send'), str_contains($name, 'publish'), str_contains($name, 'recordpayment'),
            str_contains($name, 'generate') => 'primary',
            str_contains($name, 'approve'), str_contains($name, 'confirm'), str_contains($name, 'restore'),
            str_contains($name, 'reopen'), str_contains($name, 'collected'), str_contains($name, 'issue') => 'success',
            str_contains($name, 'readyforpickup'), str_contains($name, 'assign') => 'warning',
            default => 'gray',
        };
    }

    public static function label(Action|ActionGroup $action): string
    {
        return trim(strip_tags((string) $action->getLabel()));
    }

    public static function icon(Action $action): string
    {
        $name = strtolower($action->getName());
        if (str_contains($name, 'delete')) {
            $model = $action->getRecord() ? $action->getRecord()::class : $action->getModel();

            return ! str_contains($name, 'force') && $model && in_array(SoftDeletes::class, class_uses_recursive($model), true)
                ? 'heroicon-o-archive-box' : 'heroicon-o-trash';
        }

        return 'heroicon-o-'.match (true) {
            str_contains($name, 'cancel'), str_contains($name, 'close') => 'x-mark',
            str_contains($name, 'create'), str_contains($name, 'submit'), str_contains($name, 'send') => 'paper-airplane',
            str_contains($name, 'save'), str_contains($name, 'update') => 'check',
            str_contains($name, 'archive') => 'archive-box',
            str_contains($name, 'restore'), str_contains($name, 'reopen') => 'arrow-uturn-left',
            str_contains($name, 'approve'), str_contains($name, 'confirm') => 'check-circle',
            str_contains($name, 'reject'), str_contains($name, 'revoke') => 'x-circle',
            str_contains($name, 'clearhouseholdhead') => 'user-minus',
            str_contains($name, 'head'), str_contains($name, 'assign') => 'user-circle',
            str_contains($name, 'deactivate') => 'no-symbol',
            str_contains($name, 'readyforpickup') => 'clipboard-document-check',
            $name === 'collected' => 'check-badge',
            str_contains($name, 'password') => 'key',
            str_contains($name, 'reply'), str_contains($name, 'email'), str_contains($name, 'sms') => 'paper-airplane',
            str_contains($name, 'note'), str_contains($name, 'edit'), str_contains($name, 'manage') => 'pencil-square',
            str_contains($name, 'status') => 'arrows-right-left',
            str_contains($name, 'attachment') => 'paper-clip',
            str_contains($name, 'proof') => 'arrow-up-tray',
            $name === 'receiptsetup' => 'exclamation-circle',
            $name === 'receiptnotgenerated' => 'clock',
            str_contains($name, 'receipt'), str_contains($name, 'download'), $name === 'pdf', $name === 'excel' => 'document-arrow-down',
            str_contains($name, 'print') => 'printer',
            $name === 'members' => 'users',
            default => 'eye',
        };
    }
}
