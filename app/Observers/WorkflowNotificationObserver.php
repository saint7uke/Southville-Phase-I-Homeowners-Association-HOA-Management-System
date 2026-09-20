<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\ContactMessage;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\CertificateIssued;
use App\Notifications\OperationalAlert;
use App\Notifications\WorkflowStatusChanged;
use Illuminate\Database\Eloquent\Model;

final class WorkflowNotificationObserver
{
    public function created(Model $model): void
    {
        $settings = SystemSetting::current();

        if ($model instanceof Certificate && $settings->certificate_notifications) {
            $model->homeowner()->with('user')->firstOrFail()->user->notify(new CertificateIssued($model->id));

            return;
        }

        $alert = match (true) {
            $model instanceof Complaint && $settings->complaint_notifications => new OperationalAlert('New homeowner complaint', "Complaint {$model->ticket_number}: {$model->subject}", url('/staff/complaints')),
            $model instanceof ServiceRequest && $settings->request_notifications => new OperationalAlert('New homeowner service request', "Request {$model->ticket_number}: {$model->request_type}", url('/staff/service-requests')),
            $model instanceof ContactMessage && $settings->contact_notifications => new OperationalAlert('New public contact message', "{$model->name}: {$model->subject}", url('/staff/contact-messages')),
            default => null,
        };

        if ($alert !== null) {
            User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'hoa_staff')->where('guard_name', 'web'))
                ->where('account_status', 'Active')
                ->each(fn (User $user) => $user->notify($alert));
        }
    }

    public function updated(Model $model): void
    {
        if (! $model->wasChanged('status')) {
            return;
        }

        $settings = SystemSetting::current();
        if ($model instanceof Complaint && $settings->complaint_notifications) {
            $model->homeowner()->with('user')->firstOrFail()->user->notify(new WorkflowStatusChanged('complaint', $model->ticket_number, $model->status, $model->admin_remarks));
        }
        if ($model instanceof ServiceRequest && $settings->request_notifications) {
            $model->homeowner()->with('user')->firstOrFail()->user->notify(new WorkflowStatusChanged('request', $model->ticket_number, $model->status, $model->admin_remarks));
        }
    }
}
