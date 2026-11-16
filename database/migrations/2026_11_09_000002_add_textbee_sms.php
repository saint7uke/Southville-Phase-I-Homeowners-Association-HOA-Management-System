<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->boolean('sms_enabled')->default(false);
            foreach (['approval', 'due', 'overdue', 'payment', 'complaint', 'announcement'] as $type) {
                $table->boolean('sms_'.$type.'_enabled')->default(true);
            }
            $table->boolean('sms_incoming_enabled')->default(false);
            $table->unsignedSmallInteger('sms_due_days')->default(3);
            $table->unsignedSmallInteger('sms_overdue_days')->default(7);
            $table->unsignedInteger('sms_daily_segments')->default(50);
        });
        Schema::table('announcements', fn (Blueprint $table) => $table->boolean('is_important')->default(false));
        Schema::table('complaints', fn (Blueprint $table) => $table->uuid('sms_status_version')->nullable());
        Schema::create('sms_contact_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('verified_phone_hash', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('transactional_opt_in')->default(false);
            $table->boolean('announcement_opt_in')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->string('consent_version', 30)->nullable();
            $table->timestamps();
        });
        Schema::create('sms_phone_states', function (Blueprint $table): void {
            $table->string('phone_hash', 64)->primary();
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sms_verifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('binding_hash', 64)->index();
            $table->string('phone_hash', 64)->index();
            $table->text('phone')->nullable();
            $table->string('code_hash')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('proof_expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sms_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30);
            $table->string('status', 25)->default('Queued')->index();
            $table->string('provider_batch_id', 100)->nullable()->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();
        });
        Schema::create('sms_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('sms_batches');
            $table->string('event_key', 64);
            $table->string('phone_hash', 64);
            $table->string('direction', 10)->default('outgoing');
            $table->string('type', 30);
            $table->json('user_ids');
            $table->text('context')->nullable();
            $table->text('phone')->nullable();
            $table->text('body')->nullable();
            $table->unsignedSmallInteger('segments')->default(1);
            $table->string('status', 25)->default('Queued')->index();
            $table->string('provider_message_id', 100)->nullable()->unique();
            $table->string('provider_status', 30)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->date('reserved_on')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();
            $table->unique(['event_key', 'phone_hash']);
        });
        Schema::create('sms_daily_usage', function (Blueprint $table): void {
            $table->date('day')->primary();
            $table->unsignedInteger('segments')->default(0);
        });
        Schema::create('sms_phone_daily_usage', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('phone_hash', 64);
            $table->unsignedInteger('notifications')->default(0);
            $table->unique(['day', 'phone_hash']);
        });
        Schema::create('sms_sync_states', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('announcement_sms_blasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->unique()->constrained();
            $table->foreignId('sent_by')->constrained('users');
            $table->string('version', 64);
            $table->unsignedInteger('recipients');
            $table->unsignedInteger('segments');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['announcement_sms_blasts', 'sms_sync_states', 'sms_phone_daily_usage', 'sms_daily_usage', 'sms_deliveries', 'sms_batches', 'sms_verifications', 'sms_phone_states', 'sms_contact_preferences'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('announcements', fn (Blueprint $table) => $table->dropColumn('is_important'));
        Schema::table('complaints', fn (Blueprint $table) => $table->dropColumn('sms_status_version'));
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropColumn(['sms_enabled', 'sms_approval_enabled', 'sms_due_enabled', 'sms_overdue_enabled', 'sms_payment_enabled', 'sms_complaint_enabled', 'sms_announcement_enabled', 'sms_incoming_enabled', 'sms_due_days', 'sms_overdue_days', 'sms_daily_segments']);
        });
    }
};
