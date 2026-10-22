<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CertificateTemplate;
use App\Models\CertificateType;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\CertificateTypeName;
use App\Support\OperationalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CertificateTemplates
{
    public const TOKENS = ['resident_name', 'address', 'purpose', 'issuance_date', 'cohabitant_one', 'cohabitant_two'];

    public function assertSchema(bool $preview = false): void
    {
        if (! Schema::hasColumns('certificate_templates', ['renderer', 'layout'])
            || ($preview && (! Schema::hasTable('certificate_previews') || ! Schema::hasColumn('certificates', 'issuance_key')))) {
            throw ValidationException::withMessages(['type' => __('Certificate setup is incomplete. Ask an administrator to apply pending certificate migrations.')]);
        }
    }

    public function confirmResidencySource(array $data, User $actor): CertificateTemplate
    {
        OperationalAccess::authorize($actor, 'manage_settings', true);
        $this->assertSchema(true);
        Validator::make($data, ['reviewed' => ['accepted'], 'confirmed' => ['accepted'], 'note' => ['required', 'string', 'min:10', 'max:2000']])->validate();
        app(ResidencyFont::class)->register();

        return DB::transaction(function () use ($data, $actor): CertificateTemplate {
            CertificateType::query()->where('name', 'Certificate of Residency')->lockForUpdate()->firstOrFail();
            $manifest = json_decode(file_get_contents(resource_path('certificates/residency-2026/layout.json')), true, flags: JSON_THROW_ON_ERROR);
            $settings = SystemSetting::current();
            SystemSetting::query()->whereKey($settings->id)->lockForUpdate()->firstOrFail();
            $template = CertificateTemplate::query()->where('type', 'Certificate of Residency')->where('renderer', OriginalCertificateRenderer::ID)
                ->orderByDesc('version')->get()->first(fn ($version) => $this->matchesUploadedSource($version, $manifest));
            $template = ($template ?? $this->createOriginalResidencyDraft(true, $actor))->fresh();
            // Explicit confirmation, never inferred from names in the uploaded file.
            $this->configureSignatory(['name' => $manifest['printed_signatory_name'], 'title' => $manifest['printed_signatory_title'], 'note' => $data['note'], 'confirmed' => true], $actor);
            if ($template->status === 'Draft') {
                $this->approve($template->id, $data['note'], true, $actor);
            } else {
                $this->assertOriginalSignatory($template);
                app(OriginalCertificateRenderer::class)->assertYear($template->layout, now());
                app(OriginalCertificateRenderer::class)->render($template->layout, []);
            }

            return $template->fresh();
        });
    }

    public function createOriginalResidencyDraft(bool $reviewed, User $actor): CertificateTemplate
    {
        OperationalAccess::authorize($actor, 'manage_settings', true);
        $this->assertSchema();
        Validator::make(['reviewed' => $reviewed], ['reviewed' => ['accepted']], [
            'reviewed.accepted' => __('Review the original Word document and reference PDF before registering this draft.'),
        ])->validate();
        $layout = json_decode(file_get_contents(resource_path('certificates/residency-2026/layout.json')), true, flags: JSON_THROW_ON_ERROR);
        $bytes = file_get_contents(resource_path('certificates/residency-2026/reference.pdf'));
        if ($bytes === false || ! hash_equals($layout['sha256'], hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['template' => __('The certificate background checksum does not match the approved version.')]);
        }
        $layout['background_path'] = 'certificate-backgrounds/'.$layout['sha256'].'.pdf';
        // Content-addressed backgrounds are shared by immutable versions; never overwrite differing bytes.
        if (! Storage::disk('local')->exists($layout['background_path']) && ! Storage::disk('local')->put($layout['background_path'], $bytes)) {
            Storage::disk('local')->delete($layout['background_path']);
            throw ValidationException::withMessages(['template' => __('The original certificate background could not be stored.')]);
        }
        app(OriginalCertificateRenderer::class)->render($layout, []);

        return DB::transaction(function () use ($layout, $actor): CertificateTemplate {
            CertificateType::query()->where('name', 'Certificate of Residency')->lockForUpdate()->firstOrFail();
            $template = new CertificateTemplate([
                'type' => 'Certificate of Residency', 'title' => 'C E R T I F I C A T I O N',
                'body' => 'Original Word layout. Only the designated blanks are populated.',
                'created_by' => $actor->id,
                'version' => ((int) CertificateTemplate::query()->where('type', 'Certificate of Residency')->max('version')) + 1,
            ]);
            $template->forceFill(['renderer' => OriginalCertificateRenderer::ID, 'layout' => [
                ...$layout, 'visual_reviewed_by' => $actor->id, 'visual_reviewed_at' => now()->toIso8601String(),
            ]])->save();

            return $template;
        });
    }

    public function createDraft(array $data, User $actor): CertificateTemplate
    {
        OperationalAccess::authorize($actor, 'manage_settings', true);
        if (($data['type'] ?? null) === CohabitationCertificateData::TYPE) {
            throw ValidationException::withMessages(['type' => __(CohabitationCertificateSource::SETUP_MESSAGE)]);
        }
        if (($data['type'] ?? null) === 'Certificate of Residency') {
            throw ValidationException::withMessages(['type' => __('Residency certificates use only the uploaded document. Confirm its source under Certificates.')]);
        }
        $validated = Validator::make($data, [
            'type' => ['required', Rule::in(CertificateTypeCatalog::ALLOWED)],
            'title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:10000'],
        ])->validate();
        preg_match_all('/\{\{\s*([^{}]+)\s*\}\}/', $validated['body'], $matches);
        foreach ($matches[1] as $token) {
            if (! in_array(trim($token), self::TOKENS, true)) {
                throw ValidationException::withMessages(['body' => __('Use only the listed document placeholders.')]);
            }
        }

        return DB::transaction(function () use ($validated, $actor): CertificateTemplate {
            // Existing catalog rows serialize version allocation, including the first draft.
            CertificateType::query()->where('name', $validated['type'])->lockForUpdate()->firstOrFail();

            return CertificateTemplate::query()->create([...$validated, 'created_by' => $actor->id,
                'version' => ((int) CertificateTemplate::query()->where('type', $validated['type'])->max('version')) + 1]);
        });
    }

    public function approve(int $id, string $note, bool $confirmed, User $actor): void
    {
        OperationalAccess::authorize($actor, 'manage_settings', true);
        Validator::make(['note' => trim($note), 'confirmed' => $confirmed], [
            'note' => ['required', 'string', 'min:10', 'max:2000'], 'confirmed' => ['accepted'],
        ])->validate();
        DB::transaction(function () use ($id, $actor, $note): void {
            $template = CertificateTemplate::query()->lockForUpdate()->findOrFail($id);
            if ($template->status !== 'Draft') {
                throw ValidationException::withMessages(['template' => __('Only a draft may be approved.')]);
            }
            if ($template->renderer === OriginalCertificateRenderer::ID) {
                $this->assertOriginalSignatory($template);
                app(OriginalCertificateRenderer::class)->assertYear($template->layout, now());
                app(OriginalCertificateRenderer::class)->render($template->layout, []);
            }
            $template->forceFill(['status' => 'Approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'approval_note' => trim($note)])->save();
        });
    }

    public function configureSignatory(array $data, User $actor): void
    {
        OperationalAccess::authorize($actor, 'manage_settings', true);
        $data = Validator::make($data, [
            'name' => ['required', 'string', 'max:160', "regex:/^[\pL\s'.-]+$/u"],
            'title' => ['required', 'string', 'max:160'], 'note' => ['required', 'string', 'min:10', 'max:2000'], 'confirmed' => ['accepted'],
        ])->validate();
        SystemSetting::current()->forceFill([
            'certificate_signatory_name' => trim($data['name']), 'certificate_signatory_title' => trim($data['title']),
            'signatory_confirmed_by' => $actor->id, 'signatory_confirmed_at' => now(), 'signatory_confirmation_note' => trim($data['note']),
        ])->save();
    }

    public function approved(string $type): ?CertificateTemplate
    {
        if ($type === 'Certificate of Residency') {
            $this->assertSchema();
        }

        return CertificateTemplate::query()->where('type', $type)->where('status', 'Approved')
            ->when($type === 'Certificate of Residency', fn ($query) => $query->where('renderer', OriginalCertificateRenderer::ID))
            ->orderByDesc('version')->first();
    }

    public function assertIssuable(string $type): ?CertificateTemplate
    {
        if ($type === CohabitationCertificateData::TYPE) {
            return app(CohabitationCertificateSource::class)->approved();
        }
        $template = $this->approved($type);
        if ($type === 'Certificate of Residency' && $template === null) {
            throw ValidationException::withMessages(['type' => __('Confirm the uploaded Residency source under Certificates before generating a certificate.')]);
        }
        if ($type === 'Certificate of Cohabitation' && $template === null) {
            throw ValidationException::withMessages(['type' => __('The Cohabitation template has not been approved by the HOA. Only draft previews are available.')]);
        }
        $settings = SystemSetting::current();
        if (! $settings->signatory_confirmed_at || blank($settings->certificate_signatory_name) || blank($settings->certificate_signatory_title)) {
            throw ValidationException::withMessages(['type' => __('Ask an administrator to confirm the authorized certificate signatory before issuance.')]);
        }
        if ($template?->renderer === OriginalCertificateRenderer::ID) {
            $manifest = json_decode(file_get_contents(resource_path('certificates/residency-2026/layout.json')), true, flags: JSON_THROW_ON_ERROR);
            if (! $this->matchesUploadedSource($template, $manifest)) {
                throw ValidationException::withMessages(['type' => __('Confirm the uploaded Residency source under Certificates before generating a certificate.')]);
            }
            $this->assertOriginalSignatory($template);
        }

        return $template;
    }

    private function matchesUploadedSource(CertificateTemplate $template, array $manifest): bool
    {
        foreach (['sha256', 'source_sha256', 'pages', 'fields', 'printed_year', 'printed_signatory_name', 'printed_signatory_title'] as $key) {
            if (($template->layout[$key] ?? null) != ($manifest[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** Presentation-only readiness check; never creates settings or approves a source. */
    public function isResidencySourceConfirmed(): bool
    {
        try {
            $template = $this->approved('Certificate of Residency');
            $manifestPath = resource_path('certificates/residency-2026/layout.json');
            if (! $template || ! is_file($manifestPath)) {
                return false;
            }
            $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (ValidationException|\JsonException) {
            return false;
        }

        return $this->matchesUploadedSource($template, $manifest)
            && $this->hasOriginalSignatory($template, SystemSetting::query()->find(1));
    }

    private function assertOriginalSignatory(CertificateTemplate $template): void
    {
        if (! $this->hasOriginalSignatory($template, SystemSetting::current())) {
            throw ValidationException::withMessages(['template' => __('Confirm the exact signatory name and title printed on the original template before approving or issuing it.')]);
        }
    }

    private function hasOriginalSignatory(CertificateTemplate $template, ?SystemSetting $settings): bool
    {
        return $settings?->signatory_confirmed_at !== null
            && CertificateTypeName::key($settings->certificate_signatory_name) === CertificateTypeName::key($template->layout['printed_signatory_name'] ?? '')
            && CertificateTypeName::key($settings->certificate_signatory_title) === CertificateTypeName::key($template->layout['printed_signatory_title'] ?? '');
    }

    public function render(string $body, array $values): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m): string => (string) ($values[$m[1]] ?? ''), $body) ?? '';
    }
}
