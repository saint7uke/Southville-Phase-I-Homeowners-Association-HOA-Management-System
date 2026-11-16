<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_documents', function (Blueprint $table): void {
            $table->foreignId('settlement_obligation_id')->nullable()->unique()->constrained('dues_obligations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_documents', function (Blueprint $table): void {
            $table->dropForeign(['settlement_obligation_id']);
            $table->dropUnique(['settlement_obligation_id']);
            $table->dropColumn('settlement_obligation_id');
        });
    }
};
