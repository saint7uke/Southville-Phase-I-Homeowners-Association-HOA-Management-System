<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Actions\ContactMessages\AddContactMessageNote;
use App\Actions\ContactMessages\ChangeContactMessageArchiveState;
use App\Actions\ContactMessages\ReopenContactMessage;
use App\Actions\ContactMessages\SendContactMessageReply;
use App\Actions\ContactMessages\UpdateContactMessageStatus;
use App\Filament\Concerns\HasIconOnlyRecordActions;
use App\Filament\Concerns\HasSpecificFeedback;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\User;
use App\Support\ActionFeedback;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EditContactMessage extends EditRecord
{
    use HasIconOnlyRecordActions;
    use HasSpecificFeedback;
    use MapsDomainValidation;

    protected static string $resource = ContactMessageResource::class;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendReply')->label(__('Send email reply'))->icon('heroicon-o-paper-airplane')->color('success')
                ->visible(fn (): bool => ! $this->record->trashed() && $this->record->status !== 'Closed')
                ->schema([
                    TextInput::make('subject')->default(fn (): string => 'Re: '.$this->record->subject.' ['.$this->record->reference.']')->required()->maxLength(200),
                    Textarea::make('body')->label(__('Reply'))->required()->maxLength(10000)->rows(8),
                    Hidden::make('idempotency_token')->default(fn (): string => (string) Str::uuid()),
                ])->action(function (array $data): void {
                    try {
                        $activity = $this->runValidatedDomainAction(fn () => app(SendContactMessageReply::class)->handle($this->record, $data, $this->actor()));
                        $activity->refresh();
                    } catch (ValidationException $exception) {
                        throw $exception;
                    } catch (AuthorizationException $exception) {
                        throw $exception;
                    } catch (Throwable $exception) {
                        Log::warning('Contact reply mail delivery failed.', [
                            'exception' => $exception::class,
                            'code' => 'contact_reply_transport_failure',
                        ]);
                        Notification::make()
                            ->danger()
                            ->title(__('Email reply could not be sent.'))
                            ->body(__('Please correct the mail service issue and try again.'))
                            ->send();

                        return;
                    }
                    $this->record->refresh();
                    Notification::make()
                        ->status($activity->delivery_status === 'Sent' ? 'success' : 'danger')
                        ->title($activity->delivery_status === 'Sent' ? __('Email Reply Sent Successfully!') : __('Email reply could not be sent.'))
                        ->body($activity->delivery_status === 'Sent' ? __('The mail server accepted the reply. Inbox receipt is not confirmed.')
                            : ($activity->delivery_status === 'Uncertain' ? __('The delivery result is uncertain. Ask the system operator to review it before sending another reply.') : null))
                        ->send();
                }),
            Action::make('addNote')->label(__('Add internal note'))->icon('heroicon-o-pencil-square')->color('gray')
                ->visible(fn (): bool => ! $this->record->trashed() && $this->record->status !== 'Closed')
                ->schema([Textarea::make('body')->label(__('Internal note'))->required()->maxLength(5000)->rows(6)])
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(AddContactMessageNote::class)->handle($this->record, $data['body'], $this->actor()));
                    $this->record->refresh();
                    Notification::make()->success()->title(__('Contact Inquiry Internal Note Created Successfully!'))->send();
                }),
            Action::make('changeStatus')->label(__('Update status'))->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn (): bool => ! $this->record->trashed() && $this->record->status !== 'Closed')
                ->schema([
                    Select::make('status')->options(fn (): array => $this->nextStatuses())->required(),
                    Hidden::make('expected_updated_at')->default(fn (): ?string => $this->record->updated_at?->toISOString()),
                ])->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(UpdateContactMessageStatus::class)->handle(
                        $this->record, $data['status'], $this->actor(), $data['expected_updated_at'] ?? null,
                    ));
                    $this->record->refresh();
                    Notification::make()->success()->title(__('Contact Inquiry Status Updated Successfully!'))->send();
                }),
            Action::make('reopen')->label(__('Reopen inquiry'))->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn (): bool => ! $this->record->trashed() && $this->record->status === 'Closed' && (Filament::auth()->user()?->hasRole('hoa_admin') ?? false))
                ->schema([Textarea::make('reason')->required()->minLength(10)->maxLength(2000)])
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(ReopenContactMessage::class)->handle($this->record, $data['reason'], $this->actor()));
                    $this->record->refresh();
                    ActionFeedback::success('Contact Inquiry Reopened Successfully!');
                }),
            ActionGroup::make([
                Action::make('archive')->label(__('Archive'))->icon('heroicon-o-archive-box')->color('danger')->requiresConfirmation()
                    ->visible(fn (): bool => ! $this->record->trashed())
                    ->action(function (): void {
                        app(ChangeContactMessageArchiveState::class)->archive($this->record, $this->actor());
                        ActionFeedback::success('Contact Inquiry Archived Successfully!');
                        $this->redirect(ContactMessageResource::getUrl());
                    }),
                Action::make('restore')->label(__('Restore'))->icon('heroicon-o-arrow-path')->requiresConfirmation()
                    ->visible(fn (): bool => $this->record->trashed())
                    ->action(function (): void {
                        app(ChangeContactMessageArchiveState::class)->restore($this->record, $this->actor());
                        $this->record->refresh();
                        ActionFeedback::success('Contact Inquiry Restored Successfully!');
                    }),
            ])->label(__('Record actions'))->icon('heroicon-o-ellipsis-horizontal')->button()->outlined()->color('gray')
                ->visible(fn (): bool => Filament::auth()->user()?->hasRole('hoa_admin') ?? false),
        ];
    }

    public function getTitle(): string
    {
        return __('Inquiry').' '.$this->record->reference;
    }

    public function getSubheading(): ?string
    {
        return $this->record->subject;
    }

    private function actor(): User
    {
        $actor = app(AuthenticatedActor::class)->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function nextStatuses(): array
    {
        return match ($this->record->status) {
            'New' => ['In Progress' => __('In Progress')],
            'In Progress' => ['Awaiting Resident' => __('Awaiting Resident'), 'Closed' => __('Closed')],
            'Awaiting Resident' => ['In Progress' => __('In Progress'), 'Closed' => __('Closed')],
            default => [],
        };
    }
}
