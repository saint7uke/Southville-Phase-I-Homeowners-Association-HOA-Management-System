<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

trait MapsDomainValidation
{
    protected function runValidatedDomainAction(Closure $callback, ?string $statePath = null): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('Please review the form'))->body(collect($exception->errors())->flatten()->first())->send();
            $path = $statePath ?? $this->getMountedActionSchema()?->getStatePath();
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => [
                $path && ! str_starts_with($field, $path.'.') ? $path.'.'.$field : $field => $messages,
            ])->all());
        }
    }
}
