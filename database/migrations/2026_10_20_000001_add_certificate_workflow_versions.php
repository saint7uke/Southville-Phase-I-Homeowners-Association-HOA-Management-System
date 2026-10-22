<?php

declare(strict_types=1);

use App\Support\CertificateTypeName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 64);
            $table->unsignedInteger('version');
            $table->string('title', 160);
            $table->text('body');
            $table->string('status', 16)->default('Draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();
            $table->unique(['type', 'version']);
        });
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->string('certificate_signatory_name')->nullable();
            $table->string('certificate_signatory_title')->nullable();
            $table->timestamp('signatory_confirmed_at')->nullable();
            $table->foreignId('signatory_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('signatory_confirmation_note')->nullable();
        });
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->boolean('grandfathered')->default(false);
            $table->string('cohabitant_one', 160)->nullable();
            $table->string('cohabitant_two', 160)->nullable();
            $table->json('address_snapshot')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
        });
        Schema::table('certificates', function (Blueprint $table): void {
            $table->foreignId('certificate_template_id')->nullable()->constrained('certificate_templates')->restrictOnDelete();
            $table->json('document_snapshot')->nullable();
        });
        $allowed = ['Certificate of Residency', 'Certificate of Cohabitation'];
        foreach ($allowed as $name) {
            DB::table('certificate_types')->updateOrInsert(['normalized_key' => CertificateTypeName::key($name)], [
                'name' => $name, 'is_active' => true, 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('service_requests')->orderBy('id')->chunkById(500, function ($rows) use ($allowed): void {
            foreach ($rows as $row) {
                if (! in_array(CertificateTypeName::key($row->request_type), array_map(CertificateTypeName::key(...), $allowed), true)) {
                    DB::table('service_requests')->where('id', $row->id)->update(['grandfathered' => true]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('certificate_template_id');
            $table->dropColumn('document_snapshot');
        });
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('processed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['grandfathered', 'cohabitant_one', 'cohabitant_two', 'address_snapshot', 'processed_at', 'approved_at']);
        });
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signatory_confirmed_by');
            $table->dropColumn(['certificate_signatory_name', 'certificate_signatory_title', 'signatory_confirmed_at', 'signatory_confirmation_note']);
        });
        Schema::dropIfExists('certificate_templates');
        // Imported catalog labels are deliberately retained for historical compatibility.
    }
};
