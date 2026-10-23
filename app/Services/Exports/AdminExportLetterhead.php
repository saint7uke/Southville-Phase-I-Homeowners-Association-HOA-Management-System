<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Exceptions\ExportLetterheadUnavailable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class AdminExportLetterhead
{
    public const HEADINGS_ROW = 11;

    public function imagePath(): string
    {
        $directory = config('exports.letterhead_directory');
        if (! is_string($directory) || ! is_file($directory.'/manifest.json') || ! is_file($directory.'/letterhead.png')) {
            throw new ExportLetterheadUnavailable;
        }
        $manifest = json_decode((string) file_get_contents($directory.'/manifest.json'), true);
        $path = $directory.'/letterhead.png';
        $size = @getimagesize($path);
        if (! is_array($manifest) || ! is_string($manifest['sha256'] ?? null)
            || ! hash_equals($manifest['sha256'], hash_file('sha256', $path))
            || ($manifest['source_sha256'] ?? '') !== '9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436'
            || ! $size || $size[2] !== IMAGETYPE_PNG || $size[0] !== ($manifest['width'] ?? null) || $size[1] !== ($manifest['height'] ?? null)) {
            throw new ExportLetterheadUnavailable;
        }

        return $path;
    }

    public function dataUri(): string
    {
        return 'data:image/png;base64,'.base64_encode(file_get_contents($this->imagePath()));
    }

    public function drawing(): Drawing
    {
        $drawing = new Drawing;
        $drawing->setName('HOA association letterhead');
        $drawing->setDescription('Original association logo and letterhead');
        $drawing->setPath($this->imagePath());
        $drawing->setCoordinates('A1');
        $drawing->setWidth(680);

        return $drawing;
    }

    public function format(Worksheet $sheet, string $title, string $metadata, int $columnCount, bool $landscape): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        foreach (range(1, 6) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(17);
        }
        $sheet->getRowDimension(7)->setRowHeight(6);
        $sheet->mergeCells('A8:'.$lastColumn.'8')->setCellValueExplicit('A8', $title, DataType::TYPE_STRING);
        $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(14);
        $sheet->getRowDimension(8)->setRowHeight(24);
        $sheet->mergeCells('A9:'.$lastColumn.'9')->setCellValueExplicit('A9', $metadata, DataType::TYPE_STRING);
        $sheet->getStyle('A9')->getAlignment()->setWrapText(true);
        // Merged cells do not reliably auto-fit in Excel. Reserve conservative
        // line heights so long addresses/filters remain printable, not clipped.
        $lines = array_sum(array_map(fn (string $line): int => max(1, (int) ceil(mb_strwidth($line) / max(1, $columnCount * 12))), explode("\n", $metadata)));
        $sheet->getRowDimension(9)->setRowHeight(max(44, $lines * 16));
        $sheet->getRowDimension(10)->setRowHeight(6);
        $sheet->getStyle('A11:'.$lastColumn.'11')->getFont()->setBold(true);
        $sheet->freezePane('A12');
        foreach (range(1, $columnCount) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(20);
        }
        $sheet->getStyle('A11:'.$lastColumn.$sheet->getHighestDataRow())->getAlignment()->setWrapText(true);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation($landscape ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(11, 11)
            ->setPrintArea('A1:'.$lastColumn.max(11, $sheet->getHighestDataRow()));
    }
}
