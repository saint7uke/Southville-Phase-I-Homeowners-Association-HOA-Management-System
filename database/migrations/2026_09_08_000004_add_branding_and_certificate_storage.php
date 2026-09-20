<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->string('logo_path', 500)->nullable()->after('address');
        });

        Schema::table('certificates', function (Blueprint $table): void {
            $table->string('file_path', 500)->nullable()->after('certificate_number');
        });

        Schema::create('certificate_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_sequences');

        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropColumn('file_path');
        });

        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
