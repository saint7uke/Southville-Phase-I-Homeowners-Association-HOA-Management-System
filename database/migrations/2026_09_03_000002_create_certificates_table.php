<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homeowner_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('certificate_number', 40)->unique();
            $table->string('type', 64);
            $table->string('purpose', 255)->nullable();
            $table->string('status', 16)->default('Issued');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['homeowner_id', 'status', 'issued_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
