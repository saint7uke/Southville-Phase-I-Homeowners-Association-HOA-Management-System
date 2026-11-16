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
        Schema::table('complaints', fn (Blueprint $table) => $table->string('category_other', 160)->nullable());
        $allowNullStreet = function (): void {
            foreach (['homeowners', 'households'] as $name) {
                Schema::table($name, fn (Blueprint $table) => $table->string('street', 100)->nullable()->change());
            }
        };
        if (DB::connection()->getDriverName() === 'sqlite') {
            if (DB::transactionLevel() !== 0) {
                throw new RuntimeException('Run the UI migration outside an existing SQLite transaction.');
            }
            Schema::withoutForeignKeyConstraints(function () use ($allowNullStreet): void {
                DB::transaction(function () use ($allowNullStreet): void {
                    $allowNullStreet();
                    if (DB::select('PRAGMA foreign_key_check') !== []) {
                        throw new RuntimeException('Foreign-key conflicts found; Street nullability was not changed.');
                    }
                });
            });
        } else {
            $allowNullStreet();
        }
        Schema::create('user_table_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('panel', 32);
            $table->string('table_key', 80);
            $table->json('columns');
            $table->timestamps();
            $table->unique(['user_id', 'panel', 'table_key']);
        });
        Schema::create('email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 64);
            $table->foreignId('user_id')->constrained();
            $table->string('type', 80);
            $table->string('channel', 16)->default('mail');
            $table->text('payload');
            $table->string('status', 24)->default('Queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['event_key', 'user_id', 'channel']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        // Deliberately non-destructive. Deployment rollback rolls back code, not user data.
    }
};
