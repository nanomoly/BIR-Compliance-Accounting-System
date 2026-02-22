<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->string('control_number', 40)->unique();
            $table->string('invoice_type', 20)->default('sales')->index();
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('issued_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('e_invoice_transmissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('bir-eis');
            $table->string('status', 20)->default('queued')->index();
            $table->string('reference_number', 80)->nullable()->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('transmitted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_invoice_transmissions');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
