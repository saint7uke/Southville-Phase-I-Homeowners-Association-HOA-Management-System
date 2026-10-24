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
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('status', 32)->default('New')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('contact_messages')->whereRaw('LENGTH(status) > 16')->exists()) {
            throw new RuntimeException('Cannot narrow contact_messages.status while values longer than 16 characters exist.');
        }

        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('status', 16)->default('New')->change();
        });
    }
};
