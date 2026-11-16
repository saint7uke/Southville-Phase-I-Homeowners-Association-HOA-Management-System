<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Actions;

use App\Actions\Announcements\SendImportantSms;
use App\Models\Announcement;
use App\Services\Sms\SmsEligibility;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;

final class SendImportantSmsAction
{
    public static function make(): Action
    {
        return Action::make('importantSms')->label(__('Send Important SMS'))->requiresConfirmation()
            ->visible(function (Announcement $record): bool {
                $actor = app(AuthenticatedActor::class)->user();

                return $actor && $actor->hasRole('hoa_admin') && $actor->can('manage_settings') && $actor->can('publish_announcements')
                    && app(SmsEligibility::class)->enabled('announcement') && $record->is_important
                    && Announcement::visibleToResidents()->whereKey($record->id)->exists()
                    && ! DB::table('announcement_sms_blasts')->where('announcement_id', $record->id)->exists();
            })
            ->fillForm(fn (Announcement $record): array => app(SendImportantSms::class)->review($record, app(AuthenticatedActor::class)->user()))
            ->modalDescription(__('Confirm this Important announcement SMS distribution. Publication alone does not send SMS. SIM charges and safety limits apply.'))
            ->schema([
                Hidden::make('review_hash')->required()->rules(['string', 'size:64']),
                Hidden::make('recipients'),
                Hidden::make('skipped'),
                Hidden::make('segments'),
                Placeholder::make('recipient_summary')->label(__('Eligible unique phone numbers'))->content(fn (Get $get) => (string) $get('recipients')),
                Placeholder::make('skipped_summary')->label(__('Ineligible recipients skipped'))->content(fn (Get $get) => (string) $get('skipped')),
                Placeholder::make('segment_summary')->label(__('Estimated SMS segments'))->content(fn (Get $get) => (string) $get('segments')),
            ])->action(function (Announcement $record, array $data): void {
                $count = app(SendImportantSms::class)->handle($record, app(AuthenticatedActor::class)->user(), $data['review_hash']);
                Notification::make()->success()->title(__('Important Announcement SMS Queued Successfully!'))
                    ->body(__('Unique recipients queued: :count. Gateway submission and delivery are not yet confirmed.', ['count' => $count]))->send();
            });
    }
}
