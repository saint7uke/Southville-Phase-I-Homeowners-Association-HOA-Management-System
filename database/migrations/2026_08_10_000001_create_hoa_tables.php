<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->decimal('amount', 10, 2);
            $table->string('frequency', 16);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('homeowners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('house_number', 50);
            $table->string('street', 100);
            $table->string('block', 20)->nullable();
            $table->string('lot', 20)->nullable();
            $table->date('residency_date');
            $table->string('ownership_type', 16);
            $table->string('status', 16)->default('Inactive')->index();
            $table->string('profile_photo')->nullable();
            $table->softDeletes()->index();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homeowner_id')->constrained()->restrictOnDelete();
            $table->foreignId('dues_setting_id')->constrained()->restrictOnDelete();
            $table->string('or_number', 32)->unique();
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('penalty', 10, 2)->default(0);
            $table->date('payment_date');
            $table->string('covered_period', 40);
            $table->string('payment_method', 24);
            $table->string('status', 16)->default('Paid');
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->softDeletes()->index();
            $table->timestamps();
            $table->index(['homeowner_id', 'payment_date']);
            $table->index(['status', 'payment_date']);
        });

        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homeowner_id')->constrained()->restrictOnDelete();
            $table->string('ticket_number', 32)->unique();
            $table->string('subject', 160);
            $table->text('description');
            $table->string('attachment')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('category', 32);
            $table->string('priority', 12)->default('Medium');
            $table->string('status', 24)->default('Pending');
            $table->text('admin_remarks')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->softDeletes()->index();
            $table->timestamps();
            $table->index(['homeowner_id', 'status', 'created_at']);
            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homeowner_id')->constrained()->restrictOnDelete();
            $table->string('ticket_number', 32)->unique();
            $table->string('request_type', 100);
            $table->text('details')->nullable();
            $table->string('status', 24)->default('Pending');
            $table->text('admin_remarks')->nullable();
            $table->string('document_output')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes()->index();
            $table->timestamps();
            $table->index(['homeowner_id', 'status', 'created_at']);
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->text('content');
            $table->string('banner_image')->nullable();
            $table->string('category', 24);
            $table->string('status', 16)->default('Draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->softDeletes()->index();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 120);
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('homeowners');
        Schema::dropIfExists('dues_settings');
    }
};
