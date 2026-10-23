<?php

declare(strict_types=1);

namespace App\Exports\Concerns;

use App\Services\Exports\AdminExportLetterhead;
use Maatwebsite\Excel\Events\AfterSheet;

/** Callers must explicitly opt in after verifying module, panel and format. */
trait HasAdminExportLetterhead
{
    abstract protected function usesAdminLetterhead(): bool;

    abstract protected function letterheadTitle(): string;

    abstract protected function letterheadMetadata(): string;

    abstract protected function letterheadLandscape(): bool;

    public function startCell(): string
    {
        return $this->usesAdminLetterhead() ? 'A'.AdminExportLetterhead::HEADINGS_ROW : 'A1';
    }

    public function drawings(): array
    {
        return $this->usesAdminLetterhead() ? [app(AdminExportLetterhead::class)->drawing()] : [];
    }

    public function registerEvents(): array
    {
        return $this->usesAdminLetterhead() ? [AfterSheet::class => [$this, 'formatLetterhead']] : [];
    }

    public function formatLetterhead(AfterSheet $event): void
    {
        app(AdminExportLetterhead::class)->format($event->sheet->getDelegate(), $this->letterheadTitle(), $this->letterheadMetadata(), count($this->headings()), $this->letterheadLandscape());
    }
}
