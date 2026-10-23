<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Concerns\HasAdminExportLetterhead;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

final class HouseholdSummaryExport extends DefaultValueBinder implements FromCollection, WithCustomStartCell, WithCustomValueBinder, WithDrawings, WithEvents, WithHeadings, WithMapping, WithProperties
{
    use HasAdminExportLetterhead;

    public function __construct(private readonly array $data, private readonly string $generatedBy) {}

    public function collection(): Collection
    {
        return $this->data['members'];
    }

    public function headings(): array
    {
        return [__('Name'), __('Relationship'), __('Residency status'), __('Birth date'), __('Sex')];
    }

    public function map($member): array
    {
        return [$member->full_name, __($member->relationship), __($member->residency_status), $member->date_of_birth?->toDateString(), __($member->sex ?? '')];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        // Keep names/identifiers literal, including values beginning with '='.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function properties(): array
    {
        return ['creator' => $this->generatedBy, 'title' => $this->letterheadTitle(), 'description' => $this->letterheadMetadata()];
    }

    protected function usesAdminLetterhead(): bool
    {
        return true;
    }

    protected function letterheadTitle(): string
    {
        return __('Household summary');
    }

    protected function letterheadMetadata(): string
    {
        return __('PROVISIONAL RBI — subject to HOA confirmation')."\n"
            .$this->data['household']->identifier.' — '.$this->data['household']->full_address."\n"
            .__('Current members').': '.$this->data['members']->count().' | '.__('Generated').' '.now()->format('Y-m-d H:i').' | '.$this->generatedBy;
    }

    protected function letterheadLandscape(): bool
    {
        return false;
    }
}
