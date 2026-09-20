<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues_obligations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homeowner_id')->constrained()->restrictOnDelete();
            $table->foreignId('dues_setting_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('billing_year');
            $table->unsignedTinyInteger('billing_month');
            $table->date('due_date');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status', 16)->default('Pending');
            $table->timestamps();

            $table->unique(['homeowner_id', 'dues_setting_id', 'billing_year', 'billing_month'], 'dues_obligations_month_unique');
            $table->index(['status', 'due_date', 'id']);
            $table->index(['homeowner_id', 'billing_year', 'billing_month']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('dues_obligation_id')->nullable()->after('dues_setting_id')->constrained()->nullOnDelete();
            $table->string('proof_path')->nullable()->after('notes');
            $table->string('proof_original_name', 255)->nullable()->after('proof_path');
            $table->string('proof_mime_type', 100)->nullable()->after('proof_original_name');
            $table->unsignedBigInteger('proof_size')->nullable()->after('proof_mime_type');
            $table->timestamp('reviewed_at')->nullable()->after('proof_size');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('review_status', 16)->default('Recorded')->after('reviewed_by');
            $table->index(['dues_obligation_id', 'payment_date']);
            $table->index(['review_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['dues_obligation_id', 'payment_date']);
            $table->dropIndex(['review_status', 'created_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['proof_path', 'proof_original_name', 'proof_mime_type', 'proof_size', 'reviewed_at', 'review_status']);
            $table->dropConstrainedForeignId('dues_obligation_id');
        });

        Schema::dropIfExists('dues_obligations');
    }
};
