<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropForeign(['active_driver_id']);
            $table->dropColumn('active_driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->unsignedBigInteger('active_driver_id')->nullable();
            $table->foreign('active_driver_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};