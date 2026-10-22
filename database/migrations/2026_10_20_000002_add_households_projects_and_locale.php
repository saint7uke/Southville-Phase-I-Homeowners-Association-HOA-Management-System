<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('preferred_locale', 3)->nullable());
        Schema::create('households', function (Blueprint $table): void {
            $table->id();
            $table->uuid('identifier')->unique();
            $table->string('address_key', 64)->unique();
            $table->string('house_number', 50);
            $table->string('street', 100);
            $table->string('block', 20);
            $table->string('lot', 20);
            $table->string('phase', 50);
            $table->foreignId('registered_homeowner_id')->nullable()->constrained('homeowners')->restrictOnDelete();
            $table->string('contact_number', 11)->nullable();
            $table->string('occupancy', 255)->nullable();
            $table->boolean('needs_review')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->restrictOnDelete();
            $table->foreignId('homeowner_id')->nullable()->constrained('homeowners')->restrictOnDelete();
            $table->foreignId('current_homeowner_id')->nullable()->unique()->constrained('homeowners')->restrictOnDelete();
            $table->string('full_name', 255);
            $table->string('relationship', 40)->default('Other');
            $table->date('date_of_birth')->nullable();
            $table->string('sex', 32)->nullable();
            $table->string('residency_status', 32)->default('Resident');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'ended_at']);
        });
        Schema::table('households', fn (Blueprint $table) => $table->foreignId('head_member_id')->nullable()->constrained('household_members')->restrictOnDelete());
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->text('description');
            $table->date('project_date');
            $table->string('completion_status', 32)->default('Planned');
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('project_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('mime_type', 32);
            $table->string('alt_text', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_images');
        Schema::dropIfExists('projects');
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // Avoid SQLite rebuilding a populated, referenced parent table merely to remove its circular head FK.
            DB::table('households')->update(['head_member_id' => null]);
        } else {
            Schema::table('households', fn (Blueprint $table) => $table->dropConstrainedForeignId('head_member_id'));
        }
        Schema::dropIfExists('household_members');
        Schema::dropIfExists('households');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('preferred_locale'));
    }
};
