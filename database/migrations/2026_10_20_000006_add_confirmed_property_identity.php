<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $apply = function (): void {
            foreach (['homeowners', 'households'] as $tableName) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('house_number', 50)->nullable()->change();
                    $table->string('phase_suffix', 10)->nullable();
                    $table->string('property_key', 64)->nullable()->index();
                });
            }
        };
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite rebuilds tables for nullability changes. Disable FK enforcement BEFORE
            // the transaction, then verify every FK before committing. Never cascade old rows.
            if (DB::transactionLevel() !== 0) {
                throw new RuntimeException('Run the property-identity migration outside an existing SQLite transaction.');
            }
            Schema::withoutForeignKeyConstraints(function () use ($apply): void {
                DB::transaction(function () use ($apply): void {
                    $apply();
                    if (DB::select('PRAGMA foreign_key_check') !== []) {
                        throw new RuntimeException('Property-identity migration found foreign-key conflicts; no data repair was attempted.');
                    }
                });
            });
        } else {
            $apply();
        }
        // No guessed suffixes or historical grouping changes. Confirmation is a separate action.
    }

    public function down(): void
    {
        foreach (['households', 'homeowners'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['property_key']);
                $table->dropColumn(['phase_suffix', 'property_key']);
            });
        }
        // Keep house_number nullable: restoring NOT NULL would destroy valid new registrations.
    }
};
