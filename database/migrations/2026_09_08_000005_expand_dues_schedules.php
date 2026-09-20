<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dues_settings', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('frequency');
            $table->unsignedTinyInteger('due_day')->default(10)->after('description');
            $table->date('starts_on')->nullable()->after('due_day');
            $table->date('ends_on')->nullable()->after('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('dues_settings', function (Blueprint $table): void {
            $table->dropColumn(['description', 'due_day', 'starts_on', 'ends_on']);
        });
    }
};
