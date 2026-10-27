<?php

declare(strict_types=1);

use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['homeowners', 'households'] as $table) {
            DB::table($table)
                ->whereNotNull('phase_suffix')
                ->orderBy('id')
                ->chunkById(250, function ($records) use ($table): void {
                    foreach ($records as $record) {
                        $address = [
                            'phase' => PropertyAddress::DEFAULT_PHASE,
                            'phase_suffix' => $record->phase_suffix,
                            'block' => $record->block,
                            'lot' => $record->lot,
                        ];

                        DB::table($table)->where('id', $record->id)->update([
                            'phase' => PropertyAddress::DEFAULT_PHASE,
                            'property_key' => PropertyIdentity::confirmedKey($address),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (DB::table('homeowners')->whereNotNull('phase_suffix')->exists()
            || DB::table('households')->whereNotNull('phase_suffix')->exists()) {
            throw new RuntimeException('Confirmed property phases cannot be safely restored to non-canonical legacy values.');
        }
    }
};
