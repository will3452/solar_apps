<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solar_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('unit', 40);
            $table->decimal('buying_cost', 14, 2)->nullable();
            $table->string('role')->default('material');
            $table->string('quantity_rule')->default('fixed');
            $table->decimal('quantity_factor', 12, 4)->default(1);
            $table->unsignedInteger('group_size')->default(6);
            $table->decimal('default_quantity', 12, 3)->default(1);
            $table->boolean('is_system_item')->default(false);
            $table->boolean('requires_battery')->default(false);
            $table->boolean('optional')->default(false);
            $table->boolean('active')->default(true);
            $table->string('circuit')->nullable();
            $table->string('specification_status')->default('source_unverified');
            $table->json('specs')->nullable();
            $table->json('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('solar_catalog_dependencies', function (Blueprint $table): void {
            $table->foreignId('parent_id')->constrained('solar_catalog_items')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('solar_catalog_items')->cascadeOnDelete();
            $table->primary(['parent_id', 'child_id']);
        });
        Schema::create('solar_estimates', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('customer_name');
            $table->text('address')->nullable();
            $table->json('inputs');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_estimates');
        Schema::dropIfExists('solar_catalog_dependencies');
        Schema::dropIfExists('solar_catalog_items');
    }
};
