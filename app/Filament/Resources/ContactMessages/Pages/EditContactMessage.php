<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Actions\ContactMessages\AddContactMessageNote;
use App\Actions\ContactMessages\ChangeContactMessageArchiveState;
use App\Actions\ContactMessages\ReopenContactMessage;
use App\Actions\ContactMessages\SendContactMessageReply;
use App\Actions\ContactMessages\UpdateContactMessageStatus;
use App\Filament\Concerns\MapsDomainValidation;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\User;
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
use Illuminate\Support\Str;

final class EditContactMessage extends EditRecord
{
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
                    $this->runValidatedDomainAction(fn () => app(SendContactMessageReply::class)->handle($this->record, $data, $this->actor()));
                    $this->record->refresh();
                    Notification::make()->success()->title(__('Reply queued for delivery.'))->send();
                }),
            Action::make('addNote')->label(__('Add internal note'))->icon('heroicon-o-pencil-square')->color('gray')
                ->visible(fn (): bool => ! $this->record->trashed() && $this->record->status !== 'Closed')
                ->schema([Textarea::make('body')->label(__('Internal note'))->required()->maxLength(5000)->rows(6)])
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(AddContactMessageNote::class)->handle($this->record, $data['body'], $this->actor()));
                    $this->record->refresh();
                    Notification::make()->success()->title(__('Internal note saved.'))->send();
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
                    Notification::make()->success()->title(__('Inquiry status updated.'))->send();
                }),
            Action::make('reopen')->label(__('Reopen inquiry'))->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn (): bool => ! $this->record->trashed() && $this->record->status === 'Closed' && (Filament::auth()->user()?->hasRole('hoa_admin') ?? false))
                ->schema([Textarea::make('reason')->required()->minLength(10)->maxLength(2000)])
                ->action(function (array $data): void {
                    $this->runValidatedDomainAction(fn () => app(ReopenContactMessage::class)->handle($this->record, $data['reason'], $this->actor()));
                    $this->record->refresh();
                }),
            ActionGroup::make([
                Action::make('archive')->label(__('Archive'))->icon('heroicon-o-archive-box')->color('danger')->requiresConfirmation()
                    ->visible(fn (): bool => ! $this->record->trashed())
                    ->action(function (): void {
                        app(ChangeContactMessageArchiveState::class)->archive($this->record, $this->actor());
                        $this->redirect(ContactMessageResource::getUrl());
                    }),
                Action::make('restore')->label(__('Restore'))->icon('heroicon-o-arrow-path')
                    ->visible(fn (): bool => $this->record->trashed())
                    ->action(function (): void {
                        app(ChangeContactMessageArchiveState::class)->restore($this->record, $this->actor());
                        $this->record->refresh();
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
