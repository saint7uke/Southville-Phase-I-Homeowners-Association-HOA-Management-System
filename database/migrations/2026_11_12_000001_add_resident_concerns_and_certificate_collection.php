<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('certificates', function (Blueprint $table): void {
            $table->timestamp('ready_for_pickup_at')->nullable();
            $table->foreignId('ready_for_pickup_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collected_by');
            $table->dropConstrainedForeignId('ready_for_pickup_by');
            $table->dropColumn(['ready_for_pickup_at', 'collected_at']);
        });
        Schema::table('contact_messages', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
    }
};
