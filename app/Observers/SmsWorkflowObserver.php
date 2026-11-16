<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Complaint;
use App\Models\User;
use App\Services\Sms\SmsEnrollment;
use App\Services\Sms\SmsEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class SmsWorkflowObserver
{
    public function updated(Model $model): void
    {
        if ($model instanceof User && $model->wasChanged('contact_number') && Schema::hasTable('sms_contact_preferences')) {
            app(SmsEnrollment::class)->invalidateChangedPhone($model);
        }
        if ($model instanceof Complaint && $model->wasChanged('status')) {
            app(SmsEvents::class)->complaint($model);
        }
    }
}
