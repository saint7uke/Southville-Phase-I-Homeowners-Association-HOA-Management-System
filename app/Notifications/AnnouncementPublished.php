<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\SystemSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class AnnouncementPublished extends Notification
{
    public function __construct(private readonly Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $announcement = $this->announcement->loadMissing('creator');
        $hoaName = SystemSetting::current()->hoa_name ?: config('app.name');
        $sender = $announcement->creator?->full_name ?: $hoaName;
        $content = Str::limit(Str::squish(strip_tags(html_entity_decode($announcement->content))), 600);

        return (new MailMessage)
            ->subject(__('New HOA announcement: :title', ['title' => $announcement->title]))
            ->greeting(__('Hello :name.', ['name' => $notifiable->full_name]))
            ->line($announcement->title)
            ->line($content)
            ->line(__('Published by :sender on behalf of :hoa.', ['sender' => $sender, 'hoa' => $hoaName]))
            ->action(__('Read announcement'), url('/homeowner/announcements/'.$announcement->getKey()));
    }
}
