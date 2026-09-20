<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farm_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('eggs_per_tray')->default(30);
            $table->timestamps();
        });

        Schema::create('egg_grades', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('weight_range')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->date('production_date')->unique();
            $table->unsignedInteger('total_eggs');
            $table->unsignedInteger('damaged_eggs')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('egg_gradings', function (Blueprint $table) {
            $table->id();
            $table->date('grading_date')->index();
            $table->foreignId('egg_grade_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date')->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('egg_grade_id')->constrained()->restrictOnDelete();
            $table->string('unit', 10);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->unsignedInteger('normalized_egg_quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->date('adjustment_date')->index();
            $table->foreignId('egg_grade_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date')->index();
            $table->string('title');
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('egg_gradings');
        Schema::dropIfExists('productions');
        Schema::dropIfExists('egg_grades');
        Schema::dropIfExists('farm_settings');
    }
};
