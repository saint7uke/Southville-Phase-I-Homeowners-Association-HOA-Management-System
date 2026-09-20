<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('audience', 16)->default('Public')->after('category');
            $table->timestamp('expires_at')->nullable()->after('published_at');
            $table->index(['status', 'audience', 'published_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex(['status', 'audience', 'published_at']);
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn(['audience', 'expires_at']);
        });
    }
};
