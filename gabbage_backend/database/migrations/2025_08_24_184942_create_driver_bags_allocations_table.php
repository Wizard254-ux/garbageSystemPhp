<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_bags_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('users');
            $table->foreignId('driver_id')->constrained('users');
            $table->integer('allocated_bags')->default(0);
            $table->integer('used_bags')->default(0);
            $table->integer('available_bags')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_bags_allocations');
    }
};