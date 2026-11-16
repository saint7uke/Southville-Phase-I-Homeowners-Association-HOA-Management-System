<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class ActionFeedback
{
    public const MESSAGES = [
        'Homeowner' => [
            'create' => 'Homeowner Created Successfully!',
            'update' => 'Homeowner Information Updated Successfully!',
            'archive' => 'Homeowner Archived Successfully!',
            'delete' => 'Homeowner Deleted Successfully!',
            'restore' => 'Homeowner Restored Successfully!',
        ],
        'Household' => [
            'create' => 'Household Created Successfully!',
            'update' => 'Household Information Updated Successfully!',
            'archive' => 'Household Archived Successfully!',
            'delete' => 'Household Deleted Successfully!',
            'restore' => 'Household Restored Successfully!',
        ],
        'HouseholdMember' => [
            'create' => 'Resident Created Successfully!',
            'update' => 'Resident Updated Successfully!',
            'archive' => 'Resident Archived Successfully!',
            'delete' => 'Resident Deleted Successfully!',
            'restore' => 'Resident Restored Successfully!',
        ],
        'Complaint' => [
            'create' => 'Complaint Submitted Successfully!',
            'update' => 'Complaint Updated Successfully!',
            'archive' => 'Complaint Archived Successfully!',
            'delete' => 'Complaint Deleted Successfully!',
            'restore' => 'Complaint Restored Successfully!',
        ],
        'ServiceRequest' => [
            'create' => 'Service Request Submitted Successfully!',
            'update' => 'Service Request Updated Successfully!',
            'archive' => 'Service Request Archived Successfully!',
            'delete' => 'Service Request Deleted Successfully!',
            'restore' => 'Service Request Restored Successfully!',
            'approve' => 'Service Request Approved Successfully!',
            'reject' => 'Service Request Rejected Successfully!',
        ],
        'Announcement' => [
            'create' => 'Announcement Created Successfully!',
            'update' => 'Announcement Updated Successfully!',
            'archive' => 'Announcement Archived Successfully!',
            'delete' => 'Announcement Deleted Successfully!',
            'restore' => 'Announcement Restored Successfully!',
            'publish' => 'Announcement Published Successfully!',
        ],
        'User' => [
            'create' => 'User Account Created Successfully!',
            'update' => 'User Account Updated Successfully!',
            'archive' => 'User Account Archived Successfully!',
            'delete' => 'User Account Deleted Successfully!',
            'restore' => 'User Account Restored Successfully!',
        ],
        'Role' => [
            'create' => 'Role Created Successfully!',
            'update' => 'Role Updated Successfully!',
            'archive' => 'Role Archived Successfully!',
            'delete' => 'Role Deleted Successfully!',
            'restore' => 'Role Restored Successfully!',
        ],
        'Certificate' => [
            'create' => 'Certificate Generated Successfully!',
            'update' => 'Certificate Updated Successfully!',
            'archive' => 'Certificate Archived Successfully!',
            'delete' => 'Certificate Deleted Successfully!',
            'restore' => 'Certificate Restored Successfully!',
        ],
        'Payment' => [
            'create' => 'Payment Record Created Successfully!',
            'update' => 'Payment Record Updated Successfully!',
            'archive' => 'Payment Record Archived Successfully!',
            'delete' => 'Payment Record Deleted Successfully!',
            'restore' => 'Payment Record Restored Successfully!',
        ],
        'DuesSetting' => [
            'create' => 'Dues Settings Created Successfully!',
            'update' => 'Dues Settings Updated Successfully!',
            'archive' => 'Dues Settings Archived Successfully!',
            'delete' => 'Dues Settings Deleted Successfully!',
            'restore' => 'Dues Settings Restored Successfully!',
        ],
        'SystemSetting' => [
            'create' => 'System Settings Created Successfully!',
            'update' => 'System Settings Updated Successfully!',
            'archive' => 'System Settings Archived Successfully!',
            'delete' => 'System Settings Deleted Successfully!',
            'restore' => 'System Settings Restored Successfully!',
        ],
        'ContactMessage' => [
            'create' => 'Contact Inquiry Created Successfully!',
            'update' => 'Contact Inquiry Updated Successfully!',
            'archive' => 'Contact Inquiry Archived Successfully!',
            'delete' => 'Contact Inquiry Deleted Successfully!',
            'restore' => 'Contact Inquiry Restored Successfully!',
        ],
        'AuditLog' => [
            'create' => 'Audit Log Created Successfully!',
            'update' => 'Audit Log Updated Successfully!',
            'archive' => 'Audit Log Archived Successfully!',
            'delete' => 'Audit Log Deleted Successfully!',
            'restore' => 'Audit Log Restored Successfully!',
        ],
        'Project' => [
            'create' => 'Project Created Successfully!',
            'update' => 'Project Updated Successfully!',
            'archive' => 'Project Archived Successfully!',
            'delete' => 'Project Deleted Successfully!',
            'restore' => 'Project Restored Successfully!',
        ],
    ];

    public static function title(string $model, string $operation): string
    {
        $key = class_basename($model);
        $message = self::MESSAGES[$key][$operation] ?? throw new \InvalidArgumentException("Unknown feedback operation: {$key}.{$operation}");

        return __($message);
    }

    public static function configureAction(Action $action): void
    {
        $operation = match (true) {
            $action instanceof ForceDeleteAction, $action instanceof ForceDeleteBulkAction => 'delete',
            $action instanceof DeleteAction, $action instanceof DeleteBulkAction => 'archive',
            $action instanceof RestoreAction, $action instanceof RestoreBulkAction => 'restore',
            $action instanceof CreateAction => 'create',
            $action instanceof EditAction => 'update',
            default => null,
        };
        if ($operation === null) {
            return;
        }
        $action->successNotificationTitle(function () use ($action, $operation): string {
            $model = $action->getModel();
            $actualOperation = $operation === 'archive' && ! in_array(SoftDeletes::class, class_uses_recursive($model), true) ? 'delete' : $operation;

            return self::title($model, $actualOperation);
        });
        $action->failureNotificationTitle(function () use ($action, $operation): string {
            $model = $action->getModel();
            $actualOperation = $operation === 'archive' && ! in_array(SoftDeletes::class, class_uses_recursive($model), true) ? 'delete' : $operation;

            return __('Unable to :operation :module. Please review the record and try again.', [
                'operation' => __($actualOperation), 'module' => $action->getModelLabel(),
            ]);
        });
        if ($action instanceof BulkAction) {
            $action->failureNotificationTitle(function (int $successCount, int $totalCount) use ($action, $operation): string {
                $actualOperation = $operation === 'archive' && ! in_array(SoftDeletes::class, class_uses_recursive($action->getModel()), true) ? 'delete' : $operation;

                return __(':module bulk :operation incomplete: :success of :total records completed.', [
                    'module' => $action->getPluralModelLabel(), 'operation' => __($actualOperation),
                    'success' => $successCount, 'total' => $totalCount,
                ]);
            });
            $action->successNotification(fn (Notification $notification) => $notification->body(
                __('Records affected: :count', ['count' => $action->getTotalSelectedRecordsCount()]),
            ));
        }
    }

    public static function after(string $title, Closure $operation): mixed
    {
        $result = $operation();
        self::success($title);

        return $result;
    }

    public static function success(string $title): void
    {
        Notification::make()->success()->title(__($title))->send();
    }

    public static function fromNotification(Notification $notification): array
    {
        return ['id' => $notification->getId(), 'level' => $notification->getStatus() ?? 'info',
            'title' => $notification->getTitle(), 'message' => $notification->getBody()];
    }

    public static function flash(string $level, string $title, string $message): array
    {
        return ['id' => (string) Str::uuid(), 'level' => $level, 'title' => __($title), 'message' => $message];
    }
}
