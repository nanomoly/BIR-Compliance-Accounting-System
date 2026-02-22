<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tin', 20)->index();
            $table->string('registered_address');
            $table->string('software_version')->default('1.0.0');
            $table->string('database_version')->nullable();
            $table->string('developer_name');
            $table->string('developer_tin', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();
            $table->string('code', 15)->unique();
            $table->string('name');
            $table->string('tin', 20)->nullable();
            $table->string('address');
            $table->boolean('is_main')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('company_profiles');
    }
};
