<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('hoa_name', 160)->default('Southville Phase I Homeowners Association');
            $table->string('contact_email', 255)->nullable();
            $table->string('address', 500)->default('Brgy. Inocencio, Trece Martires City, Cavite');
            $table->unsignedTinyInteger('delinquency_months')->default(3);
            $table->boolean('complaint_notifications')->default(true);
            $table->boolean('request_notifications')->default(true);
            $table->boolean('announcement_notifications')->default(true);
            $table->boolean('certificate_notifications')->default(true);
            $table->boolean('delinquency_notifications')->default(true);
            $table->boolean('contact_notifications')->default(true);
            $table->boolean('staff_panel_enabled')->default(true);
            $table->boolean('homeowner_panel_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('system_settings')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
