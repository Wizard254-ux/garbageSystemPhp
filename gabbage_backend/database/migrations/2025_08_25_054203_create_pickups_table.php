<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('route_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->enum('pickup_status', ['unpicked', 'picked', 'skipped'])->default('unpicked');
            $table->date('pickup_date'); // The date this pickup is scheduled/completed
            $table->timestamp('picked_at')->nullable(); // When actually picked up
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('set null');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('set null');
            
            // Ensure one pickup per client per week
            $table->unique(['client_id', 'pickup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickups');
    }
};