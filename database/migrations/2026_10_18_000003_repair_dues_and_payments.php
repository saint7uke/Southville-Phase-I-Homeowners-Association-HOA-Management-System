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
        Schema::table('dues_settings', function (Blueprint $table): void {
            $table->string('normalized_name', 120)->nullable()->after('name')->index();
        });

        Schema::create('dues_setting_name_locks', function (Blueprint $table): void {
            $table->string('normalized_name', 120)->primary();
            $table->timestamps();
        });

        DB::table('dues_settings')->orderBy('id')->get()->each(function (object $setting): void {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $setting->name) ?? (string) $setting->name);
            $key = mb_strtolower(class_exists(Normalizer::class)
                ? (Normalizer::normalize($name, Normalizer::FORM_KC) ?: $name)
                : $name, 'UTF-8');
            $start = $setting->starts_on ?: substr((string) $setting->created_at, 0, 10);

            DB::table('dues_settings')->where('id', $setting->id)->update([
                'name' => $name,
                'normalized_name' => $key,
                'starts_on' => $start,
            ]);
            DB::table('dues_setting_name_locks')->insertOrIgnore([
                'normalized_name' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('dues_settings', function (Blueprint $table): void {
            $table->date('starts_on')->nullable(false)->change();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('payer_homeowner_id')->nullable()->after('homeowner_id')->constrained('homeowners')->restrictOnDelete();
            $table->string('transaction_reference', 120)->nullable()->after('payment_method');
            $table->string('transaction_reference_key', 120)->nullable()->after('transaction_reference')->unique();
            $table->uuid('submission_token')->nullable()->after('transaction_reference_key')->unique();
            $table->text('review_notes')->nullable()->after('review_status');
        });

        DB::table('payments')->orderBy('id')->get(['id', 'homeowner_id'])->each(function (object $payment): void {
            DB::table('payments')->where('id', $payment->id)->update([
                'payer_homeowner_id' => $payment->homeowner_id,
                'submission_token' => (string) Str::uuid(),
            ]);
        });

        Schema::table('system_settings', function (Blueprint $table): void {
            $table->boolean('payment_notifications')->default(true)->after('request_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->dropColumn('payment_notifications');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['transaction_reference_key']);
            $table->dropUnique(['submission_token']);
            $table->dropConstrainedForeignId('payer_homeowner_id');
            $table->dropColumn(['transaction_reference', 'transaction_reference_key', 'submission_token', 'review_notes']);
        });

        Schema::dropIfExists('dues_setting_name_locks');
        Schema::table('dues_settings', function (Blueprint $table): void {
            $table->dropIndex(['normalized_name']);
            $table->date('starts_on')->nullable()->change();
            $table->dropColumn('normalized_name');
        });
    }
};
