<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Actions;

use App\Actions\Dues\GenerateCurrentMonthDues;
use App\Support\AuthenticatedActor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Throwable;

final class GenerateCurrentMonthDuesAction
{
    public static function make(): Action
    {
        return Action::make('generateCurrentDues')
            ->label(__('Generate current-month dues'))
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (): bool => (app(AuthenticatedActor::class)->user()?->can('manage_dues') ?? false))
            ->requiresConfirmation()
            ->modalDescription(__('Generate missing dues for the current month using active schedules. Existing obligations and payments will not be changed.'))
            ->action(function (): void {
                $actor = app(AuthenticatedActor::class)->user();
                abort_unless($actor !== null, 403);
                try {
                    $count = app(GenerateCurrentMonthDues::class)->handle($actor);
                    Notification::make()->success()->title($count > 0 ? "Created {$count} dues obligations." : 'No new dues were needed.')
                        ->body(__('Refresh the obligation list to see available unpaid dues.'))->send();
                } catch (ValidationException $exception) {
                    Notification::make()->warning()->title($exception->validator->errors()->first())->send();
                } catch (AuthorizationException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->danger()->title(__('Unable to generate dues. Please try again or contact the administrator.'))->send();
                }
            });
    }
}
