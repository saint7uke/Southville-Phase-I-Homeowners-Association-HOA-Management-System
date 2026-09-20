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
            $table->string('frequency', 32)->change();
        });
    }

    public function down(): void
    {
        // Preserve the wider column because existing "Special Assessment"
        // values cannot be represented safely by the original 16 characters.
    }
};
