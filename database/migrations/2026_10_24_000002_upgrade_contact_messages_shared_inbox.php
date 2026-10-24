<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->string('reference', 24)->nullable()->unique()->after('id');
            $table->foreignId('first_handled_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('first_handled_at')->nullable()->after('first_handled_by');
            $table->timestamp('closed_at')->nullable()->after('handled_at');
            $table->softDeletes();
        });

        DB::table('contact_messages')->orderBy('id')->chunkById(200, function ($messages): void {
            foreach ($messages as $message) {
                DB::table('contact_messages')->where('id', $message->id)->update([
                    'reference' => 'INQ-'.Str::upper(Str::random(12)),
                    'first_handled_by' => $message->handled_by,
                    'first_handled_at' => $message->handled_at,
                    'closed_at' => $message->status === 'Closed' ? ($message->handled_at ?? $message->updated_at) : null,
                ]);
            }
        });

        Schema::table('contact_messages', fn (Blueprint $table) => $table->string('reference', 24)->nullable(false)->change());

        Schema::create('contact_message_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('recipient_email')->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->uuid('idempotency_token')->nullable()->unique();
            $table->string('delivery_status', 16)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->timestamps();
            $table->index(['contact_message_id', 'created_at']);
            $table->index(['delivery_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_message_activities');
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropForeign(['first_handled_by']);
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'first_handled_by', 'first_handled_at', 'closed_at', 'deleted_at']);
        });
    }
};
