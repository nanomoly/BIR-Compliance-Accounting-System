<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 20)->index();
            $table->string('name');
            $table->string('type', 20)->index();
            $table->string('normal_balance', 6);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_control_account')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
