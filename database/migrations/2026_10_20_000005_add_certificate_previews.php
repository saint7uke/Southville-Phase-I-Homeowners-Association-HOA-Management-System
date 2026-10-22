<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->string('issuance_key', 64)->nullable()->unique();
        });
        Schema::create('certificate_previews', function (Blueprint $table): void {
            $table->string('token_hash', 64)->primary();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->json('payload');
            $table->json('snapshot');
            $table->string('fingerprint', 64);
            $table->timestamp('expires_at')->index();
            $table->foreignId('certificate_id')->nullable()->constrained('certificates')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_previews');
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropUnique(['issuance_key']);
            $table->dropColumn('issuance_key');
        });
    }
};
