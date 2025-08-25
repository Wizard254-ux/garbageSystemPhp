<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('organization_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('route_id')->nullable()->constrained('routes')->onDelete('set null');
            $table->enum('clientType', ['residential', 'commercial'])->default('residential');
            $table->decimal('monthlyRate', 10, 2);
            $table->integer('numberOfUnits')->default(1);
            $table->enum('pickUpDay', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->default('wednesday');
            $table->integer('gracePeriod')->default(5);
            $table->date('serviceStartDate');
            $table->string('accountNumber')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};