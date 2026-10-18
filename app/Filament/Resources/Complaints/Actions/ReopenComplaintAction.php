<?php

declare(strict_types=1);

namespace App\Filament\Resources\Complaints\Actions;

use App\Actions\Complaints\ReopenComplaint;
use App\Models\Complaint;
use App\Models\User;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class ReopenComplaintAction
{
    public static function make(): Action
    {
        return Action::make('reopen')
            ->label('Reopen complaint')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Reopen resolved complaint')
            ->modalDescription('The complaint will return to Under Review. Its current assignee and previous resolution notes will be retained.')
            ->modalSubmitActionLabel('Reopen complaint')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason for reopening')
                    ->helperText('Explain what further action is required. This reason will be retained in the audit trail.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000)
                    ->rows(4),
            ])
            ->visible(function (Complaint $record): bool {
                $actor = app(AuthenticatedActor::class)->user();

                return $actor instanceof User && Gate::forUser($actor)->allows('reopen', $record);
            })
            ->action(function (Complaint $record, array $data): void {
                $actor = app(AuthenticatedActor::class)->user();
                if (! $actor instanceof User) {
                    throw new AuthorizationException;
                }

                app(ReopenComplaint::class)->handle($record, $actor, (string) ($data['reason'] ?? ''));
            })
            ->successNotificationTitle('Complaint reopened');
    }
}
