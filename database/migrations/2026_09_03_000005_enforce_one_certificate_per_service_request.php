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
        // InnoDB can replace the implicit foreign-key index with this unique
        // index. Restore its non-unique support before removing uniqueness.
        if (! Schema::hasIndex('certificates', 'certificates_service_request_id_foreign')) {
            Schema::table('certificates', function (Blueprint $table): void {
                $table->index('service_request_id', 'certificates_service_request_id_foreign');
            });
        }
        Schema::table('certificates', function (Blueprint $table): void {
            $table->dropUnique(['service_request_id']);
        });
    }
};
