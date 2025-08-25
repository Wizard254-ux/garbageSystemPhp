<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('payment_ids')->nullable()->after('status'); // Array of payment IDs that paid this invoice
            $table->decimal('paid_amount', 10, 2)->default(0)->after('payment_ids'); // Total amount paid
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'fully_paid'])->default('unpaid')->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_ids', 'paid_amount', 'payment_status']);
        });
    }
};