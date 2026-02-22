<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30)->index();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('user_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity', 100)->index();
            $table->string('route')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('status', 20)->default('completed');
            $table->timestamp('backup_at')->index();
            $table->timestamp('restore_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('report_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type', 50)->index();
            $table->string('reference_number', 50)->unique();
            $table->date('from_date')->index();
            $table->date('to_date')->index();
            $table->integer('page_count')->default(1);
            $table->timestamp('generated_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('user_logs');
        Schema::dropIfExists('audit_logs');
    }
};
