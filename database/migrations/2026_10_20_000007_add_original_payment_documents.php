<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->json('property_snapshot')->nullable());
        Schema::create('payment_document_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('source_checksum', 64)->unique();
            $table->json('manifest');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->text('approval_note');
            $table->timestamp('approved_at');
            $table->timestamps();
        });
        Schema::create('payment_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_document_source_id')->constrained()->restrictOnDelete();
            $table->string('source_checksum', 64);
            $table->json('snapshot');
            $table->string('path');
            $table->string('file_checksum', 64);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['payment_id', 'source_checksum']);
        });
        Schema::create('payment_document_previews', function (Blueprint $table): void {
            $table->string('token_hash', 64)->primary();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->string('fingerprint', 64);
            $table->timestamp('expires_at')->index();
            $table->foreignId('payment_document_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_document_previews');
        Schema::dropIfExists('payment_documents');
        Schema::dropIfExists('payment_document_sources');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('property_snapshot'));
    }
};
