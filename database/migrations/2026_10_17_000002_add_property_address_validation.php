<?php

declare(strict_types=1);

use App\Support\PropertyAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homeowners', function (Blueprint $table): void {
            $table->char('address_key', 64)->nullable()->index();
        });

        Schema::create('property_address_registry', function (Blueprint $table): void {
            $table->char('address_key', 64)->primary();
            $table->timestamps();
        });

        DB::table('homeowners')->orderBy('id')->chunkById(250, function ($homeowners): void {
            foreach ($homeowners as $homeowner) {
                $key = PropertyAddress::key((array) $homeowner);
                DB::table('homeowners')->where('id', $homeowner->id)->update(['address_key' => $key]);
                DB::table('property_address_registry')->insertOrIgnore([
                    'address_key' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_address_registry');
        Schema::table('homeowners', function (Blueprint $table): void {
            $table->dropIndex(['address_key']);
            $table->dropColumn('address_key');
        });
    }
};
