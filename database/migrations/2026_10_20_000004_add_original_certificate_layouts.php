<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table): void {
            $table->string('renderer', 32)->default('text-v1');
            $table->json('layout')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', fn (Blueprint $table) => $table->dropColumn(['renderer', 'layout']));
    }
};
