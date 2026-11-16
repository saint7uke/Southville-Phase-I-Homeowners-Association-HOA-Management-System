<?php

declare(strict_types=1);

namespace App\Actions\Announcements;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\SmsDelivery;
use App\Models\User;
use App\Services\Sms\SmsBudget;
use App\Services\Sms\SmsEligibility;
use App\Services\Sms\WorkflowSms;
use App\Support\PhilippineMobile;
use App\Support\SmsText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SendImportantSms
{
    public function authorize(User $actor): void
    {
        abort_unless(! $actor->trashed() && $actor->account_status === 'Active' && $actor->hasRole('hoa_admin')
            && $actor->can('manage_settings') && $actor->can('manage_announcements') && $actor->can('publish_announcements'), 403);
    }

    public function review(Announcement $announcement, User $actor): array
    {
        $this->authorize($actor);
        $eligible = app(SmsEligibility::class);
        if (! $eligible->enabled('announcement') || ! $announcement->is_important || ! Announcement::visibleToResidents()->whereKey($announcement->id)->exists()) {
            throw ValidationException::withMessages(['announcement' => __('Only an active Important announcement can be selected for SMS distribution.')]);
        }
        $users = User::role('homeowner')->where('account_status', 'Active')->whereHas('homeowner', fn ($query) => $query->whereIn('status', ['Active', 'Delinquent']))->get();
        $numbers = $users->map(fn (User $user) => $eligible->recipient($user, 'announcement'))->filter()->unique()->sort()->values();
        $version = SmsEligibility::announcementVersion($announcement);
        $fingerprints = $numbers->map(PhilippineMobile::fingerprint(...))->all();

        return ['version' => $version, 'review_hash' => hash('sha256', $version.json_encode($fingerprints, JSON_THROW_ON_ERROR)),
            'recipients' => $numbers->count(), 'skipped' => $users->filter(fn ($user) => $eligible->recipient($user, 'announcement') === null)->count(),
            'segments' => $numbers->count() * SmsText::segments(SmsText::TEMPLATES['announcement'])];
    }

    public function handle(Announcement $announcement, User $actor, mixed $reviewHash): int
    {
        $this->authorize($actor);
        if (! is_string($reviewHash) || ! preg_match('/^[a-f0-9]{64}$/D', $reviewHash)) {
            throw ValidationException::withMessages(['announcement' => __('Announcement or recipients changed. Reopen the SMS confirmation.')]);
        }

        return DB::transaction(function () use ($announcement, $actor, $reviewHash): int {
            $announcement = Announcement::lockForUpdate()->findOrFail($announcement->id);
            $review = $this->review($announcement, $actor);
            if (! hash_equals($review['review_hash'], $reviewHash)) {
                throw ValidationException::withMessages(['announcement' => __('Announcement or recipients changed. Reopen the SMS confirmation.')]);
            }
            if (DB::table('announcement_sms_blasts')->where('announcement_id', $announcement->id)->exists()) {
                throw ValidationException::withMessages(['announcement' => __('This announcement has already been selected for SMS distribution.')]);
            }
            if ($review['recipients'] === 0) {
                throw ValidationException::withMessages(['announcement' => __('No verified, consenting SMS recipients are eligible.')]);
            }
            $ids = app(WorkflowSms::class)->queue('announcement', 'announcement:'.$announcement->id,
                User::role('homeowner')->where('account_status', 'Active')->get(), ['announcement_id' => $announcement->id, 'version' => $review['version']]);
            $deliveries = SmsDelivery::whereIn('batch_id', $ids)->where('status', 'Queued')->get();
            if ($deliveries->count() !== $review['recipients'] || ! app(SmsBudget::class)->reserve($deliveries)) {
                throw ValidationException::withMessages(['announcement' => __('The SMS safety budget cannot cover this complete announcement. Nothing was queued.')]);
            }
            DB::table('announcement_sms_blasts')->insert(['announcement_id' => $announcement->id, 'sent_by' => $actor->id,
                'version' => $review['version'], 'recipients' => $review['recipients'], 'segments' => $review['segments'], 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::create(['user_id' => $actor->id, 'panel' => 'admin', 'action' => 'Announcement.sms_blast', 'auditable_type' => Announcement::class,
                'auditable_id' => $announcement->id, 'new_values' => ['recipients' => $review['recipients'], 'segments' => $review['segments']]]);

            return $review['recipients'];
        }, 3);
    }
}
