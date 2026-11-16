<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_email_blasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamp('queued_at');
            $table->timestamps();
        });

        Schema::create('announcement_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blast_id')->constrained('announcement_email_blasts')->cascadeOnDelete();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('Queued')->index();
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
            $table->index(['blast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_email_deliveries');
        Schema::dropIfExists('announcement_email_blasts');
    }
};
