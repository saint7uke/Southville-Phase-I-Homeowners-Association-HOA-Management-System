<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\CohabitationCertificateData;
use App\Services\ResidencyFont;
use App\Support\OriginalLayoutPdf;
use Illuminate\Support\Facades\Storage;

/** Synthetic blank artwork for pipeline tests ONLY. Never ships as the HOA certificate. */
trait UsesSyntheticCohabitation
{
    protected function syntheticCohabitationSource(): array
    {
        if (! is_file((string) config('certificates.cohabitation_font'))) {
            $this->markTestSkipped('Original Cohabitation rendering requires the licensed Arial font.');
        }
        $pdf = new OriginalLayoutPdf;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage('P', [612, 792]);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Text(40, 20, 'SYNTHETIC TEST SOURCE - NOT AN HOA DOCUMENT');
        $bytes = $pdf->Output('', 'S');
        $docx = 'SYNTHETIC CORRECTED SOURCE ONLY; NOT A REAL WORD EXPORT';
        $keys = ['cohabitant_one', 'cohabitant_two', 'address_one', 'address_two', 'phase_suffix', 'years_together', 'requester_name', 'day', 'month'];
        foreach (['one', 'two'] as $person) {
            foreach (['month', 'day', 'year'] as $part) {
                $keys[] = 'cohabitant_'.$person.'_birth_'.$part;
            }
        }
        $fields = [];
        foreach ($keys as $index => $key) {
            $error = match (true) {
                str_contains($key, '_birth_') => preg_replace('/_(month|day|year)$/', '_date', $key),
                in_array($key, ['address_one', 'address_two', 'phase_suffix', 'day', 'month'], true) => 'homeowner_id',
                $key === 'requester_name' => 'requester',
                default => $key,
            };
            $fields[] = ['key' => $key, 'page' => 1, 'x' => 40, 'y' => 60 + $index * 25, 'width' => 530, 'height' => 15, 'font_size' => 12, 'error_field' => $error];
        }
        $layout = ['document_type' => CohabitationCertificateData::TYPE, 'font_profile' => 'cohabitation', 'phase_is_blank' => true,
            'source_sha256' => hash('sha256', $docx), 'sha256' => hash('sha256', $bytes),
            'font_sha256' => app(ResidencyFont::class)->checksum('cohabitation'), 'printed_year' => 2026,
            'printed_signatory_name' => 'SYNTHETIC COHABITATION OFFICER', 'printed_signatory_title' => 'TEST ONLY',
            'pages' => [['width' => 612, 'height' => 792]], 'fields' => $fields];
        Storage::disk('local')->put('synthetic-cohabitation/original.docx', $docx);
        Storage::disk('local')->put('synthetic-cohabitation/reference.pdf', $bytes);
        Storage::disk('local')->put('synthetic-cohabitation/layout.json', json_encode($layout, JSON_THROW_ON_ERROR));
        config(['certificates.cohabitation_source_directory' => Storage::disk('local')->path('synthetic-cohabitation')]);

        return $layout;
    }
}
