<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\User;
use App\Support\OperationalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Original source metadata only: no template editor, inferred approval or substitute artwork. */
final class CohabitationCertificateSource
{
    public const UNADJUSTED_SOURCE_SHA256 = '9b572c18fc08ecc0edc909dced3069b3ead51f29c1be6beb6737cc0a3a0fe436';

    public const SETUP_MESSAGE = 'The corrected original Cohabitation document with a blank phase suffix and its reviewed PDF mapping are required. Contact the administrator.';

    /** Read-only readiness information; never stages, approves or repairs a source. */
    public function setupStatus(): array
    {
        $directory = config('certificates.cohabitation_source_directory');
        $requirements = [];
        foreach ([
            'original.docx' => 'Corrected original DOCX containing Phase 1 - ____.',
            'reference.pdf' => 'Reviewed PDF exported from the corrected original.',
            'layout.json' => 'Reviewed blank-field mapping for that PDF.',
        ] as $file => $requirement) {
            if (! is_string($directory) || ! is_file($directory.'/'.$file)) {
                $requirements[] = $requirement;
            }
        }
        if ($requirements === []) {
            try {
                $layout = $this->manifest();
                app(CertificateTemplates::class)->assertSchema(true);
                if (! hash_equals((string) ($layout['font_sha256'] ?? ''), app(ResidencyFont::class)->checksum('cohabitation'))) {
                    throw ValidationException::withMessages(['type' => __('The reviewed Cohabitation font does not match the installed font. Ask an administrator to review the source configuration.')]);
                }

                return $this->isConfirmed()
                    ? ['state' => 'confirmed', 'label' => 'Confirmed', 'message' => 'This source version is confirmed. Preview and issue certificates without repeating setup.', 'requirements' => []]
                    : ['state' => 'ready', 'label' => 'Ready for review', 'message' => 'Review the original document and confirm its printed signatory once for this source version. This is not required for each certificate.', 'requirements' => []];
            } catch (ValidationException $exception) {
                $requirements = collect($exception->errors())->flatten()->all();
            }
        }

        return ['state' => 'awaiting', 'label' => 'Awaiting corrected document',
            'message' => 'Original document setup is incomplete. Review and confirmation are unavailable until these requirements are resolved.',
            'requirements' => $requirements];
    }

    public function manifest(): array
    {
        $directory = config('certificates.cohabitation_source_directory');
        if (! is_string($directory) || ! is_file($directory.'/layout.json') || ! is_file($directory.'/original.docx') || ! is_file($directory.'/reference.pdf')) {
            $this->invalid();
        }
        try {
            $layout = json_decode(file_get_contents($directory.'/layout.json'), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->invalid();
        }
        if (! is_array($layout) || ($layout['document_type'] ?? null) !== CohabitationCertificateData::TYPE
            || ($layout['font_profile'] ?? null) !== 'cohabitation' || ($layout['phase_is_blank'] ?? null) !== true
            || ($layout['source_sha256'] ?? null) === self::UNADJUSTED_SOURCE_SHA256
            || empty($layout['pages']) || ! is_array($layout['pages']) || ! is_array($layout['fields'] ?? null)
            || ! is_int($layout['printed_year'] ?? null) || blank($layout['printed_signatory_name'] ?? null) || blank($layout['printed_signatory_title'] ?? null)
            || ! hash_equals((string) ($layout['source_sha256'] ?? ''), hash_file('sha256', $directory.'/original.docx'))
            || ! hash_equals((string) ($layout['sha256'] ?? ''), hash_file('sha256', $directory.'/reference.pdf'))) {
            $this->invalid();
        }
        $required = ['cohabitant_one', 'cohabitant_two', 'address_one', 'address_two', 'phase_suffix', 'years_together', 'requester_name', 'day', 'month'];
        foreach (['one', 'two'] as $person) {
            foreach (['month', 'day', 'year'] as $part) {
                $required[] = 'cohabitant_'.$person.'_birth_'.$part;
            }
        }
        if (array_diff($required, array_column($layout['fields'], 'key'))) {
            $this->invalid();
        }
        foreach ($layout['pages'] as $page) {
            if (! is_numeric($page['width'] ?? null) || ! is_numeric($page['height'] ?? null) || $page['width'] <= 0 || $page['height'] <= 0) {
                $this->invalid();
            }
        }
        foreach ($layout['fields'] as $field) {
            $page = $layout['pages'][($field['page'] ?? 0) - 1] ?? null;
            if (! $page || ! in_array($field['key'] ?? null, [...$required, 'address_continuation'], true)
                || ! in_array($field['error_field'] ?? null, [...CohabitationCertificateData::FIELDS, 'address_continuation', 'homeowner_id'], true)) {
                $this->invalid();
            }
            foreach (['x', 'y', 'width', 'height', 'font_size'] as $number) {
                if (! is_numeric($field[$number] ?? null) || $field[$number] < 0) {
                    $this->invalid();
                }
            }
            if ($field['width'] <= 0 || $field['height'] <= 0 || $field['font_size'] <= 0
                || $page['width'] < $field['x'] + $field['width'] || $page['height'] < $field['y'] + $field['height']) {
                $this->invalid();
            }
        }
        $layout['background_path'] = 'certificate-backgrounds/'.$layout['sha256'].'.pdf';

        return $layout;
    }

    public function pdf(): string
    {
        $this->manifest();

        return file_get_contents(config('certificates.cohabitation_source_directory').'/reference.pdf');
    }

    public function draft(User $actor): CertificateTemplate
    {
        abort_unless(OperationalAccess::allows($actor, 'manage_requests') || OperationalAccess::allows($actor, 'manage_settings', true), 403);
        app(CertificateTemplates::class)->assertSchema(true);
        $layout = $this->manifest();
        if (! hash_equals((string) ($layout['font_sha256'] ?? ''), app(ResidencyFont::class)->checksum('cohabitation'))) {
            $this->invalid();
        }
        if (! Storage::disk('local')->exists($layout['background_path']) && ! Storage::disk('local')->put($layout['background_path'], $this->pdf())) {
            throw ValidationException::withMessages(['type' => __('The original certificate background could not be stored.')]);
        }
        app(OriginalCertificateRenderer::class)->render($layout, []);

        return DB::transaction(function () use ($layout, $actor): CertificateTemplate {
            CertificateType::where('name', CohabitationCertificateData::TYPE)->lockForUpdate()->firstOrFail();
            $existing = CertificateTemplate::where('type', CohabitationCertificateData::TYPE)->orderByDesc('version')->get()
                ->first(fn ($row) => $this->matches($row, $layout));
            if ($existing) {
                return $existing;
            }
            $template = new CertificateTemplate(['type' => CohabitationCertificateData::TYPE, 'title' => 'Original Cohabitation document',
                'body' => 'Original document. Only reviewed blank fields are populated.', 'created_by' => $actor->id,
                'version' => ((int) CertificateTemplate::where('type', CohabitationCertificateData::TYPE)->max('version')) + 1]);
            $template->forceFill(['renderer' => OriginalCertificateRenderer::ID, 'layout' => $layout])->save();

            return $template;
        });
    }

    public function confirm(array $data, User $actor): CertificateTemplate
    {
        OperationalAccess::authorize($actor, 'manage_settings', true);
        Validator::make($data, ['reviewed' => ['accepted'], 'confirmed' => ['accepted'], 'note' => ['required', 'string', 'min:10', 'max:2000']])->validate();
        $draft = $this->draft($actor);

        return DB::transaction(function () use ($draft, $data, $actor): CertificateTemplate {
            $template = CertificateTemplate::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($template->status === 'Approved') {
                return $this->approved();
            }
            app(OriginalCertificateRenderer::class)->assertYear($template->layout, now());
            $template->forceFill(['layout' => [...$template->layout, 'signatory_confirmation' => [
                'name' => $template->layout['printed_signatory_name'], 'title' => $template->layout['printed_signatory_title'],
                'actor_id' => $actor->id, 'confirmed_at' => now()->toIso8601String(), 'note' => trim($data['note'])]],
                'status' => 'Approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'approval_note' => trim($data['note'])])->save();

            return $template;
        });
    }

    public function approved(): CertificateTemplate
    {
        app(CertificateTemplates::class)->assertSchema(true);
        $layout = $this->manifest();
        $template = CertificateTemplate::where('type', CohabitationCertificateData::TYPE)->where('status', 'Approved')->orderByDesc('version')->get()
            ->first(fn ($row) => $this->matches($row, $layout));
        $confirmation = $template?->layout['signatory_confirmation'] ?? [];
        if (! $template || ! $template->approved_at || ! $template->approved_by
            || ($confirmation['name'] ?? null) !== $layout['printed_signatory_name'] || ($confirmation['title'] ?? null) !== $layout['printed_signatory_title']
            || ($confirmation['actor_id'] ?? null) !== $template->approved_by || blank($confirmation['note'] ?? null) || blank($confirmation['confirmed_at'] ?? null)) {
            throw ValidationException::withMessages(['type' => __('Confirm the original Cohabitation source and its printed signatory under Document setup before issuance.')]);
        }

        return $template;
    }

    /** A read-only lookup: opening a draft URL must not stage or activate a source. */
    public function previewTemplate(): CertificateTemplate
    {
        app(CertificateTemplates::class)->assertSchema(true);
        $layout = $this->manifest();
        $template = CertificateTemplate::where('type', CohabitationCertificateData::TYPE)->orderByDesc('version')->get()
            ->first(fn ($row) => $this->matches($row, $layout));
        if (! $template) {
            $this->invalid();
        }

        return $template;
    }

    public function isConfirmed(): bool
    {
        try {
            $this->approved();

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function matches(CertificateTemplate $template, array $layout): bool
    {
        $stored = $template->layout ?? [];
        unset($stored['signatory_confirmation']);

        return $template->renderer === OriginalCertificateRenderer::ID && $stored == $layout;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['type' => __(self::SETUP_MESSAGE)]);
    }
}
