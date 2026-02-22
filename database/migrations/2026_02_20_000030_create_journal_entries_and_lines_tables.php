<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('journal_type', 30)->index();
            $table->string('entry_number', 30)->unique();
            $table->string('control_number', 40)->unique();
            $table->date('entry_date')->index();
            $table->string('reference_no', 100)->nullable();
            $table->text('description');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('reversed_from_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('reversed_from_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('particulars')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['journal_entry_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
