<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', fn (Blueprint $table) => $table->softDeletes()->index());
    }

    public function down(): void
    {
        Schema::table('certificates', fn (Blueprint $table) => $table->dropIndex(['deleted_at']));
        Schema::table('certificates', fn (Blueprint $table) => $table->dropColumn('deleted_at'));
    }
};
