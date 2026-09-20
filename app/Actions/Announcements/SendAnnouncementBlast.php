<?php

declare(strict_types=1);

namespace App\Actions\Announcements;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Support\AuthenticatedActor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class SendAnnouncementBlast
{
    public function __construct(private readonly AuthenticatedActor $actor) {}

    public function handle(Announcement $announcement, User $sender): int
    {
        if (! $sender->hasRole('hoa_admin') || ! $sender->can('publish_announcements') || ! $announcement->published()->whereKey($announcement)->exists()) {
            throw new AuthorizationException;
        }

        if (! SystemSetting::current()->announcement_notifications) {
            throw ValidationException::withMessages(['announcement' => __('Announcement emails are disabled in System Settings.')]);
        }

        $count = 0;
        User::query()->whereHas('roles', fn ($query) => $query->where('name', 'homeowner')->where('guard_name', 'web'))->where('account_status', 'Active')->chunkById(100, function ($users) use ($announcement, &$count): void {
            foreach ($users as $user) {
                $user->notify(new AnnouncementPublished($announcement->title));
                $count++;
            }
        });

        AuditLog::query()->create([
            'user_id' => $sender->id,
            'panel' => $this->actor->panel(),
            'action' => 'Announcement.email_blast',
            'auditable_type' => $announcement->getMorphClass(),
            'auditable_id' => $announcement->id,
            'new_values' => ['recipient_count' => $count],
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500),
        ]);

        return $count;
    }
}
