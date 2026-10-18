<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('homeowners', function (Blueprint $table) {
            $table->string('landlord_name')->nullable()->after('status');
            $table->string('landlord_contact', 11)->nullable()->after('landlord_name');
            $table->date('lease_start_date')->nullable()->after('landlord_contact');
            $table->date('lease_end_date')->nullable()->after('lease_start_date');
            $table->string('verification_document_path')->nullable()->after('lease_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('homeowners', function (Blueprint $table) {
            $table->dropColumn([
                'landlord_name',
                'landlord_contact',
                'lease_start_date',
                'lease_end_date',
                'verification_document_path',
            ]);
        });
    }
};
