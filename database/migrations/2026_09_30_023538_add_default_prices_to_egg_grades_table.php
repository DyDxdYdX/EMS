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
        Schema::table('egg_grades', function (Blueprint $table) {
            $table->decimal('price_per_egg', 10, 2)->nullable();
            $table->decimal('price_per_tray', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('egg_grades', function (Blueprint $table) {
            $table->dropColumn(['price_per_egg', 'price_per_tray']);
        });
    }
};
