<?php

declare(strict_types=1);

namespace App\Actions\Announcements;

use App\Jobs\SendAnnouncementEmail;
use App\Models\Announcement;
use App\Models\AnnouncementEmailBlast;
use App\Models\AnnouncementEmailDelivery;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SendAnnouncementBlast
{
    public function __construct(private readonly AuthenticatedActor $actor) {}

    public function handle(Announcement $announcement, User $sender): int
    {
        $this->authorize($announcement, $sender);

        return DB::transaction(function () use ($announcement, $sender): int {
            $lockedAnnouncement = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            $this->authorize($lockedAnnouncement, $sender);

            if (AnnouncementEmailBlast::query()->where('announcement_id', $lockedAnnouncement->id)->exists()) {
                throw ValidationException::withMessages([
                    'announcement' => __('This announcement email blast has already been created. Only failed deliveries may be retried.'),
                ]);
            }

            $recipients = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'homeowner')->where('guard_name', 'web'))
                ->whereHas('homeowner', fn ($query) => $query->where('status', 'Active'))
                ->where('account_status', 'Active')
                ->whereNotNull('email')
                ->get()
                ->filter(fn (User $user): bool => filter_var($user->email, FILTER_VALIDATE_EMAIL) !== false);

            $now = now();
            $blast = AnnouncementEmailBlast::query()->create([
                'announcement_id' => $lockedAnnouncement->id,
                'sent_by' => $sender->id,
                'recipient_count' => $recipients->count(),
                'queued_at' => $now,
            ]);

            $deliveryIds = $recipients->map(function (User $recipient) use ($blast, $lockedAnnouncement, $now): int {
                return AnnouncementEmailDelivery::query()->create([
                    'blast_id' => $blast->id,
                    'announcement_id' => $lockedAnnouncement->id,
                    'user_id' => $recipient->id,
                    'status' => 'Queued',
                    'queued_at' => $now,
                ])->id;
            })->all();

            AuditLog::query()->create([
                'user_id' => $sender->id,
                'panel' => $this->actor->panel(),
                'action' => 'Announcement.email_blast',
                'auditable_type' => $lockedAnnouncement->getMorphClass(),
                'auditable_id' => $lockedAnnouncement->id,
                'new_values' => ['recipient_count' => count($deliveryIds), 'blast_id' => $blast->id],
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500),
            ]);

            DB::afterCommit(function () use ($deliveryIds): void {
                foreach ($deliveryIds as $deliveryId) {
                    SendAnnouncementEmail::dispatch($deliveryId)->onConnection('sync');
                }
            });

            return count($deliveryIds);
        });
    }

    public function retryFailed(Announcement $announcement, User $sender): int
    {
        $this->authorize($announcement, $sender);

        return DB::transaction(function () use ($announcement, $sender): int {
            $deliveryIds = AnnouncementEmailDelivery::query()
                ->where('announcement_id', $announcement->id)
                ->where('status', 'Failed')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if ($deliveryIds === []) {
                throw ValidationException::withMessages(['announcement' => __('There are no failed announcement emails to retry.')]);
            }

            AnnouncementEmailDelivery::query()->whereKey($deliveryIds)->update([
                'status' => 'Queued',
                'queued_at' => now(),
                'sent_at' => null,
                'failed_at' => null,
                'failure_code' => null,
                'updated_at' => now(),
            ]);

            AuditLog::query()->create([
                'user_id' => $sender->id,
                'panel' => $this->actor->panel(),
                'action' => 'Announcement.email_retry',
                'auditable_type' => $announcement->getMorphClass(),
                'auditable_id' => $announcement->id,
                'new_values' => ['recipient_count' => count($deliveryIds)],
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500),
            ]);

            DB::afterCommit(function () use ($deliveryIds): void {
                foreach ($deliveryIds as $deliveryId) {
                    SendAnnouncementEmail::dispatch($deliveryId)->onConnection('sync');
                }
            });

            return count($deliveryIds);
        });
    }

    private function authorize(Announcement $announcement, User $sender): void
    {
        if (! $sender->hasRole('hoa_admin')
            || ! $sender->can('publish_announcements')
            || ! $announcement->published()->whereKey($announcement)->exists()) {
            throw new AuthorizationException;
        }
        if (! SystemSetting::current()->announcement_notifications) {
            throw ValidationException::withMessages(['announcement' => __('Announcement emails are disabled in System Settings.')]);
        }
    }
}
