<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bag_id')->constrained('bags')->onDelete('cascade');
            $table->string('client_email');
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->integer('number_of_bags_issued');
            $table->string('otp_code', 6);
            $table->timestamp('otp_expires_at');
            $table->boolean('is_verified')->default(false);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bag_issues');
    }
};