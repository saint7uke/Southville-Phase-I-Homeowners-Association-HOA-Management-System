<?php

declare(strict_types=1);

use App\Models\ServiceRequest;
use App\Support\CertificateTypeName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64);
            $table->char('normalized_key', 64)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('certificates', function (Blueprint $table): void {
            $table->foreignId('certificate_type_id')->nullable()->after('type')->constrained('certificate_types')->restrictOnDelete();
        });

        $now = now();
        $systemKeys = collect(ServiceRequest::DEFAULT_CERTIFICATE_TYPES)
            ->mapWithKeys(fn (string $name): array => [CertificateTypeName::key($name) => $name]);

        foreach ($systemKeys as $key => $name) {
            DB::table('certificate_types')->insert([
                'name' => $name,
                'normalized_key' => $key,
                'is_active' => true,
                'is_system' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('certificates')->select('type')->distinct()->orderBy('type')->each(function (object $record) use ($systemKeys, $now): void {
            $name = CertificateTypeName::normalize($record->type);
            if ($name === '') {
                return;
            }

            $key = CertificateTypeName::key($name);
            DB::table('certificate_types')->insertOrIgnore([
                'name' => $systemKeys[$key] ?? $name,
                'normalized_key' => $key,
                'is_active' => true,
                'is_system' => $systemKeys->has($key),
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $typeId = DB::table('certificate_types')->where('normalized_key', $key)->value('id');
            DB::table('certificates')->where('type', $record->type)->update(['certificate_type_id' => $typeId]);
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('certificate_type_id');
        });

        Schema::dropIfExists('certificate_types');
    }
};
