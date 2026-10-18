<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['review_status', 'deleted_at', 'payment_date', 'id'], 'payments_report_collection_index');
        });
        Schema::table('dues_obligations', function (Blueprint $table): void {
            $table->index(['due_date', 'billing_year', 'billing_month'], 'dues_obligations_report_period_index');
        });
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->index(['status', 'deleted_at', 'created_at', 'id'], 'service_requests_report_index');
        });
        Schema::table('certificates', function (Blueprint $table): void {
            $table->index(['status', 'deleted_at', 'issued_at', 'id'], 'certificates_report_index');
        });
        Schema::table('complaints', function (Blueprint $table): void {
            $table->index(['status', 'deleted_at', 'created_at', 'id'], 'complaints_report_index');
        });
        Schema::table('homeowners', function (Blueprint $table): void {
            $table->index(['status', 'deleted_at', 'residency_date', 'id'], 'homeowners_report_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex('payments_report_collection_index'));
        Schema::table('dues_obligations', fn (Blueprint $table) => $table->dropIndex('dues_obligations_report_period_index'));
        Schema::table('service_requests', fn (Blueprint $table) => $table->dropIndex('service_requests_report_index'));
        Schema::table('certificates', fn (Blueprint $table) => $table->dropIndex('certificates_report_index'));
        Schema::table('complaints', fn (Blueprint $table) => $table->dropIndex('complaints_report_index'));
        Schema::table('homeowners', fn (Blueprint $table) => $table->dropIndex('homeowners_report_index'));
    }
};
