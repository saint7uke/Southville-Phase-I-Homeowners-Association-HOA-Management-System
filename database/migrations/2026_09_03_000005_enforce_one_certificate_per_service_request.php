<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->unique('service_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropUnique(['service_request_id']);
        });
    }
};
