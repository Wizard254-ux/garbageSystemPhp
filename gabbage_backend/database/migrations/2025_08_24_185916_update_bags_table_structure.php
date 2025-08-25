<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bags', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['number_of_bags', 'description', 'created_by']);
            $table->integer('total_bags')->default(0);
            $table->integer('allocated_bags')->default(0);
            $table->integer('available_bags')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('bags', function (Blueprint $table) {
            $table->dropColumn(['total_bags', 'allocated_bags', 'available_bags']);
            $table->integer('number_of_bags');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
        });
    }
};