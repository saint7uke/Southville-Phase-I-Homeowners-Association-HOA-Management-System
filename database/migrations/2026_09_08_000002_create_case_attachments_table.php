<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path')->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
            $table->index(['service_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_attachments');
    }
};
