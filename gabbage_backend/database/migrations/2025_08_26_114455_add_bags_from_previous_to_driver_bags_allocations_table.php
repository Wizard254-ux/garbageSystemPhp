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
        Schema::table('driver_bags_allocations', function (Blueprint $table) {
            $table->integer('bags_from_previous')->default(0)->after('available_bags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_bags_allocations', function (Blueprint $table) {
            $table->dropColumn('bags_from_previous');
        });
    }
};
