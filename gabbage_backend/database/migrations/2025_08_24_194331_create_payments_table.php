<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('trans_id')->unique(); // TransID from Safaricom
            $table->string('account_number'); // BillRefNumber from customer
            $table->unsignedBigInteger('client_id')->nullable(); // Found client
            $table->unsignedBigInteger('organization_id')->nullable(); // Client's organization
            $table->decimal('amount', 10, 2); // TransAmount
            $table->string('phone_number'); // MSISDN
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('status', ['not_allocated', 'partially_allocated', 'fully_allocated'])->default('not_allocated');
            $table->json('invoices_processed')->nullable(); // Array of invoice IDs processed
            $table->decimal('allocated_amount', 10, 2)->default(0); // Amount already allocated to invoices
            $table->decimal('remaining_amount', 10, 2)->default(0); // Amount not yet allocated
            $table->timestamp('trans_time'); // TransTime from Safaricom
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('organization_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};