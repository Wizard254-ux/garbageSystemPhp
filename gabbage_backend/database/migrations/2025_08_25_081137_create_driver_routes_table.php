<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('route_id');
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->useCurrent();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
            
            // Prevent duplicate active assignments
            $table->unique(['driver_id', 'route_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_routes');
    }
};