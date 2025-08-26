<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('to_driver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('organization_id')->constrained('users')->onDelete('cascade');
            $table->integer('number_of_bags');
            $table->string('otp_code', 6);
            $table->timestamp('otp_expires_at');
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bag_transfers');
    }
};