<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ADDRESS_UNIQUE_INDEX = 'homeowners_block_lot_unique';

    public function up(): void
    {
        $addresses = [];
        $hasDuplicateAddresses = false;

        DB::table('homeowners')
            ->select(['id', 'block', 'lot'])
            ->orderBy('id')
            ->each(function (object $homeowner) use (&$addresses, &$hasDuplicateAddresses): void {
                if ($homeowner->block === null || $homeowner->lot === null) {
                    return;
                }

                $block = mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $homeowner->block)) ?: '', 'UTF-8');
                $lot = mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $homeowner->lot)) ?: '', 'UTF-8');
                $key = $block.'|'.$lot;

                if (isset($addresses[$key])) {
                    $hasDuplicateAddresses = true;
                }

                $addresses[$key] = true;
            });

        if ($hasDuplicateAddresses) {
            throw new RuntimeException('Duplicate Block/Lot homeowner addresses must be reconciled before the profile metadata migration can run.');
        }

        Schema::table('homeowners', function (Blueprint $table): void {
            $table->string('phase', 50)->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_number', 11)->nullable();
            $table->string('profile_photo_disk', 32)->nullable();
            $table->string('profile_photo_original_name')->nullable();
            $table->string('profile_photo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('profile_photo_size')->nullable();
            $table->timestamp('profile_photo_uploaded_at')->nullable();
            $table->unique(['block', 'lot'], self::ADDRESS_UNIQUE_INDEX);
        });

        DB::table('homeowners')->orderBy('id')->each(function (object $homeowner): void {
            DB::table('homeowners')->where('id', $homeowner->id)->update([
                'block' => $homeowner->block === null ? null : mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $homeowner->block)) ?: '', 'UTF-8'),
                'lot' => $homeowner->lot === null ? null : mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $homeowner->lot)) ?: '', 'UTF-8'),
                'phase' => $homeowner->phase ?? 'Southville Phase I',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('homeowners', function (Blueprint $table): void {
            $table->dropUnique(self::ADDRESS_UNIQUE_INDEX);
            $table->dropColumn([
                'phase',
                'emergency_contact_name',
                'emergency_contact_number',
                'profile_photo_disk',
                'profile_photo_original_name',
                'profile_photo_mime_type',
                'profile_photo_size',
                'profile_photo_uploaded_at',
            ]);
        });
    }
};
