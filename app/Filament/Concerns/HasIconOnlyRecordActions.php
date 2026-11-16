<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\RecordActionIcons;
use Filament\Actions\ActionGroup;

/** Opt in only on existing-record pages, never on create/list/form actions. */
trait HasIconOnlyRecordActions
{
    public function cacheInteractsWithHeaderActions(): void
    {
        parent::cacheInteractsWithHeaderActions();

        foreach ($this->cachedHeaderActions as $action) {
            $action instanceof ActionGroup
                ? RecordActionIcons::group($action)
                : RecordActionIcons::action($action);
        }
    }
}
