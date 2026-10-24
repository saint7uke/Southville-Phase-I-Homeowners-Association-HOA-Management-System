<?php

declare(strict_types=1);

use App\Support\PropertySnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dues_obligations', function (Blueprint $table): void {
            $table->json('property_snapshot')->nullable()->after('status');
            $table->boolean('property_snapshot_needs_review')->default(false)->after('property_snapshot');
            $table->index('property_snapshot_needs_review', 'dues_obligations_property_review_index');
        });

        DB::table('dues_obligations')->orderBy('id')->chunkById(200, function ($obligations): void {
            foreach ($obligations as $obligation) {
                $snapshots = DB::table('payments')
                    ->where('dues_obligation_id', $obligation->id)
                    ->whereNotNull('property_snapshot')
                    ->pluck('property_snapshot')
                    ->map(fn (mixed $snapshot): ?array => is_array($snapshot) ? $snapshot : json_decode((string) $snapshot, true))
                    ->filter(fn (mixed $snapshot): bool => is_array($snapshot))
                    ->map(fn (array $snapshot): array => PropertySnapshot::from($snapshot))
                    ->unique(fn (array $snapshot): string => (string) $snapshot['property_key'])
                    ->values();

                DB::table('dues_obligations')->where('id', $obligation->id)->update($snapshots->count() === 1
                    ? ['property_snapshot' => json_encode($snapshots->first(), JSON_THROW_ON_ERROR), 'property_snapshot_needs_review' => false]
                    : ['property_snapshot_needs_review' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('dues_obligations', function (Blueprint $table): void {
            $table->dropIndex('dues_obligations_property_review_index');
            $table->dropColumn(['property_snapshot', 'property_snapshot_needs_review']);
        });
    }
};
