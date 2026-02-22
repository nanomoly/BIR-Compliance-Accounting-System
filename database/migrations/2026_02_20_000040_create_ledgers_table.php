<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_line_id')->constrained()->cascadeOnDelete();
            $table->date('posting_date')->index();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('running_balance', 15, 2)->default(0);
            $table->string('control_number', 40)->index();
            $table->timestamps();

            $table->index(['account_id', 'posting_date']);
            $table->index(['journal_entry_id', 'posting_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
