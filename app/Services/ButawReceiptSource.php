<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PaymentDocumentSource;
use App\Models\User;
use App\Support\OperationalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ButawReceiptSource
{
    public const LABEL = 'Monthly Dues Receipt (Resibo Butaw)';

    public const SETUP_MESSAGE = 'Receipt setup is incomplete. Ask the administrator to apply reviewed payment-document migrations.';

    public function schemaReady(): bool
    {
        return Schema::hasTable('payment_documents') && Schema::hasTable('payment_document_sources')
            && Schema::hasTable('payment_document_previews') && Schema::hasColumn('payments', 'property_snapshot');
    }

    public function manifest(): array
    {
        $directory = rtrim((string) config('certificates.butaw_source_directory'), '/\\').DIRECTORY_SEPARATOR;
        if (! is_file($directory.'layout.json') || ! is_file($directory.'reference.pdf')) {
            throw ValidationException::withMessages(['type' => __('The original Resibo Butaw PDF or reviewed blank-field mapping is missing. Ask the administrator to complete receipt setup.')]);
        }
        $layout = json_decode(file_get_contents($directory.'layout.json'), true, flags: JSON_THROW_ON_ERROR);
        // Keep earlier DOCX-derived source versions readable; the current source is the uploaded PDF itself.
        $format = $layout['source_format'] ?? 'docx';
        $original = $directory.($format === 'pdf' ? 'reference.pdf' : 'original.docx');
        if (! in_array($format, ['pdf', 'docx'], true) || ! is_file($original)
            || ! hash_equals($layout['source_sha256'] ?? '', hash_file('sha256', $original))
            || ! hash_equals($layout['sha256'] ?? '', hash_file('sha256', $directory.'reference.pdf'))
            || ($layout['printed_year'] ?? null) !== 2026 || ($layout['font_profile'] ?? null) !== 'butaw') {
            throw ValidationException::withMessages(['type' => __('The Resibo Butaw source checksum or reviewed layout is invalid.')]);
        }
        $layout['background_path'] = 'certificate-backgrounds/'.$layout['sha256'].'.pdf';

        return $layout;
    }

    public function assertSchema(): void
    {
        if (! $this->schemaReady()) {
            throw ValidationException::withMessages(['type' => __(self::SETUP_MESSAGE)]);
        }
    }

    public function review(User $actor): string
    {
        OperationalAccess::authorize($actor, 'manage_settings', true);
        OperationalAccess::authorize($actor, 'manage_payments', true);

        // Source review serves the exact original bytes. Only populated previews receive a draft watermark.
        return $this->originalPdf();
    }

    public function originalPdf(): string
    {
        $manifest = $this->manifest();
        $bytes = file_get_contents(config('certificates.butaw_source_directory').'/reference.pdf');
        if (! is_string($bytes) || ! hash_equals($manifest['sha256'], hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['type' => __('The Resibo Butaw source checksum or reviewed layout is invalid.')]);
        }

        return $bytes;
    }

    public function approve(array $data, User $actor): PaymentDocumentSource
    {
        OperationalAccess::authorize($actor, 'manage_settings', true);
        OperationalAccess::authorize($actor, 'manage_payments', true);
        $this->assertSchema();
        Validator::make($data, ['reviewed' => ['accepted'], 'note' => ['required', 'string', 'min:10', 'max:2000']])->validate();
        $manifest = $this->manifest();
        if (! hash_equals($manifest['font_sha256'] ?? '', app(ResidencyFont::class)->checksum('butaw'))) {
            throw ValidationException::withMessages(['type' => __('The original receipt font changed. Ask the HOA to review the source configuration.')]);
        }
        app(ResidencyFont::class)->register('butaw');
        $bytes = $this->originalPdf();
        if (! Storage::disk('local')->exists($manifest['background_path']) && ! Storage::disk('local')->put($manifest['background_path'], $bytes)) {
            throw ValidationException::withMessages(['type' => __('The original receipt background could not be stored.')]);
        }
        app(OriginalCertificateRenderer::class)->render($manifest, [], true);

        return DB::transaction(function () use ($manifest, $data, $actor): PaymentDocumentSource {
            DB::table('property_address_registry')->insertOrIgnore(['address_key' => $manifest['sha256'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('property_address_registry')->where('address_key', $manifest['sha256'])->lockForUpdate()->first();

            $source = PaymentDocumentSource::firstOrCreate(['source_checksum' => $manifest['sha256']], [
                'manifest' => $manifest, 'approved_by' => $actor->id, 'approved_at' => now(), 'approval_note' => $data['note'],
            ]);
            if ($source->manifest != $manifest) {
                throw ValidationException::withMessages(['type' => __('Approved document sources are immutable.')]);
            }

            return $source;
        });
    }

    public function approved(): PaymentDocumentSource
    {
        $this->assertSchema();
        $manifest = $this->manifest();
        $source = PaymentDocumentSource::where('source_checksum', $manifest['sha256'])->first();
        // MySQL JSON canonicalizes object-key order; field-list order remains significant.
        if (! $source || $source->manifest != $manifest) {
            throw ValidationException::withMessages(['type' => __('An administrator must review and confirm the original Resibo Butaw source under Certificates first.')]);
        }

        return $source;
    }

    public function isConfirmed(): bool
    {
        try {
            $this->approved();

            return true;
        } catch (ValidationException|\JsonException) {
            return false;
        }
    }
}
