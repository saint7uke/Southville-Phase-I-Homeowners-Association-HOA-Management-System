<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('last_login_panel', 20)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspension_reason', 500)->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->index(['account_status', 'last_login_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['account_status', 'last_login_at']);
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn([
                'last_login_at',
                'last_login_ip',
                'last_login_panel',
                'suspended_at',
                'suspension_reason',
                'password_changed_at',
            ]);
        });
    }
};
