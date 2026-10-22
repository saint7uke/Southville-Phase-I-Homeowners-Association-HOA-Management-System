<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\OriginalLayoutPdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/** Imports a reviewed PDF unchanged; never executes templates or imports PDF form fields. */
final class OriginalCertificateRenderer
{
    public const ID = 'original-pdf-v1';

    public function referencePreview(?array $layout = null, ?string $background = null): string
    {
        $layout ??= json_decode(file_get_contents(resource_path('certificates/residency-2026/layout.json')), true, flags: JSON_THROW_ON_ERROR);
        $layout['background_path'] = 'certificate-backgrounds/'.$layout['sha256'].'.pdf';

        return $this->build($layout, [], true, $background ?? file_get_contents(resource_path('certificates/residency-2026/reference.pdf')));
    }

    public function values(array $snapshot, CarbonInterface $date): array
    {
        if (($snapshot['layout']['font_profile'] ?? null) === 'cohabitation') {
            return app(CohabitationCertificateData::class)->values($snapshot, $date);
        }
        foreach (['resident_name' => $snapshot['resident_name'] ?? '', 'block' => $snapshot['address']['block'] ?? '', 'lot' => $snapshot['address']['lot'] ?? ''] as $value) {
            if (trim((string) $value) === '') {
                throw ValidationException::withMessages(['homeowner_id' => __('Complete the registered resident name, block and lot before generating this certificate.')]);
            }
        }

        return [
            'resident_name' => $snapshot['resident_name'] ?? '',
            'block' => $snapshot['address']['block'] ?? '',
            'lot' => $snapshot['address']['lot'] ?? '',
            'phase_suffix' => $snapshot['address']['phase_suffix'] ?? '',
            'purpose' => $snapshot['purpose'] ?? '',
            'day' => $date->format('j'),
            'month' => $date->format('F'),
        ];
    }

    public function assertYear(array $layout, CarbonInterface $date): void
    {
        if ((int) ($layout['printed_year'] ?? 0) !== (int) $date->format('Y')) {
            throw ValidationException::withMessages(['type' => __('This original certificate template is valid only for its printed year. Ask the HOA to approve a replacement.')]);
        }
    }

    public function render(array $layout, array $values, bool $draft = false): string
    {
        try {
            return $this->build($layout, $values, $draft);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['type' => __('The original certificate background could not be rendered. Contact the administrator.')]);
        }
    }

    private function build(array $layout, array $values, bool $draft, ?string $reference = null): string
    {
        $path = $layout['background_path'] ?? '';
        if (! preg_match('~^certificate-backgrounds/[a-f0-9]{64}\.pdf$~D', $path)) {
            throw ValidationException::withMessages(['type' => __('The original certificate background could not be rendered. Contact the administrator.')]);
        }
        $background = $reference ?? Storage::disk('local')->get($path);
        if (! is_string($background) || ! hash_equals((string) ($layout['sha256'] ?? ''), hash('sha256', $background))) {
            throw ValidationException::withMessages(['type' => __('The certificate background checksum does not match the approved version.')]);
        }

        $pdf = new OriginalLayoutPdf;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellHeightRatio(1);
        $pdf->SetCreator('SHOAI');
        $font = $values === [] ? null : app(ResidencyFont::class)->register($layout['font_profile'] ?? 'residency');
        if ($font !== null && isset($layout['font_sha256']) && ! hash_equals($layout['font_sha256'], $font['checksum'])) {
            throw ValidationException::withMessages(['type' => __('The certificate font changed. Generate a fresh preview.')]);
        }
        if ($font !== null) {
            $pdf->AddFont($font['name'], '', $font['definition']);
        }
        $pageCount = $pdf->setSourceFile(StreamReader::createByString($background));
        if ($pageCount !== count($layout['pages'] ?? [])) {
            throw ValidationException::withMessages(['type' => __('The original certificate page dimensions do not match the reviewed layout.')]);
        }
        for ($page = 1; $page <= $pageCount; $page++) {
            // No external links, annotations, JavaScript or interactive fields are imported.
            $id = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($id);
            $expected = $layout['pages'][$page - 1];
            if (abs($size['width'] - $expected['width']) > 0.1 || abs($size['height'] - $expected['height']) > 0.1) {
                throw ValidationException::withMessages(['type' => __('The original certificate page dimensions do not match the reviewed layout.')]);
            }
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useImportedPage($id, 0, 0, $size['width'], $size['height']);
            foreach ($layout['fields'] as $field) {
                if ($field['page'] !== $page) {
                    continue;
                }
                $value = (string) ($values[$field['key']] ?? '');
                if ($value === '') {
                    continue;
                }
                if (preg_match('/[\p{C}\r\n]/u', $value)) {
                    throw ValidationException::withMessages([$this->errorField($field['key'], $layout) => __('Enter a single printable line for the certificate field.')]);
                }
                $fontSize = (float) $field['font_size'];
                foreach (mb_str_split($value) as $character) {
                    if (! array_key_exists(mb_ord($character), $font['characters'])) {
                        throw ValidationException::withMessages([$this->errorField($field['key'], $layout) => __('The original font does not support a character in this field. Ask the HOA to review it.')]);
                    }
                }
                $pdf->SetFont($font['name'], '', $fontSize);
                if ($pdf->GetStringWidth($value) > $field['width'] || $pdf->getStringHeight($field['width'], $value) > $field['height']) {
                    throw ValidationException::withMessages([$this->errorField($field['key'], $layout) => __(($layout['font_profile'] ?? null) === 'butaw'
                        ? 'The payment details do not fit the original receipt blank. Use the detailed payment receipt or contact the HOA; the original font and layout cannot be resized.'
                        : 'The value is too long for the original certificate blank. Shorten the purpose or ask the HOA to review the resident details.')]);
                }
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetXY($field['x'], $field['y']);
                $pdf->Cell($field['width'], $field['height'], $value, 0, 0, 'C', false, '', 0, false, 'T', 'M');
            }
            if ($draft) {
                $pdf->StartTransform();
                $pdf->SetAlpha(0.25);
                $pdf->Rotate(35, $size['width'] / 2, $size['height'] / 2);
                $pdf->SetFont('helvetica', 'B', 24);
                $pdf->SetTextColor(180, 0, 0);
                $watermark = 'DRAFT - NOT VALID FOR OFFICIAL USE';
                $pdf->Text(($size['width'] - $pdf->GetStringWidth($watermark)) / 2, $size['height'] / 2, $watermark);
                $pdf->StopTransform();
                $pdf->SetAlpha(1);
            }
        }

        return $pdf->Output('', 'S');
    }

    private function errorField(string $key, array $layout): string
    {
        if (($layout['font_profile'] ?? null) === 'cohabitation') {
            return collect($layout['fields'])->firstWhere('key', $key)['error_field'] ?? 'type';
        }
        if (($layout['font_profile'] ?? null) === 'butaw') {
            return 'payment_id';
        }

        return $key === 'purpose' ? 'purpose' : 'homeowner_id';
    }
}
