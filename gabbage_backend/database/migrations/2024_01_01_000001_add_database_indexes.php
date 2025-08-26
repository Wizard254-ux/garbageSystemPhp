<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index(['email']);
            $table->index(['role']);
            $table->index(['organization_id']);
            $table->index(['isActive']);
            $table->index(['role', 'organization_id']);
            $table->index(['role', 'isActive']);
        });

        // Clients table indexes
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['organization_id']);
            $table->index(['user_id']);
            $table->index(['route_id']);
            $table->index(['organization_id', 'user_id']);
        });

        // Driver bags allocations indexes
        Schema::table('driver_bags_allocations', function (Blueprint $table) {
            $table->index(['driver_id']);
            $table->index(['organization_id']);
            $table->index(['status']);
            $table->index(['driver_id', 'status']);
        });

        // Activity logs indexes
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['user_id']);
            $table->index(['action']);
            $table->index(['created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Routes table indexes
        Schema::table('routes', function (Blueprint $table) {
            $table->index(['organization_id']);
            $table->index(['isActive']);
            $table->index(['organization_id', 'isActive']);
        });

        // Bags table indexes
        Schema::table('bags', function (Blueprint $table) {
            $table->index(['organization_id']);
        });

        // Payments table indexes
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['organization_id']);
            $table->index(['client_id']);
            $table->index(['account_number']);
            $table->index(['phone_number']);
            $table->index(['trans_id']);
            $table->index(['status']);
            $table->index(['payment_method']);
            $table->index(['created_at']);
            $table->index(['trans_time']);
            $table->index(['client_id', 'status']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'created_at']);
        });

        // Invoices table indexes
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['organization_id']);
            $table->index(['client_id']);
            $table->index(['payment_status']);
            $table->index(['status']);
            $table->index(['due_date']);
            $table->index(['created_at']);
            $table->index(['invoice_number']);
            $table->index(['type']);
            $table->index(['client_id', 'payment_status']);
            $table->index(['organization_id', 'payment_status']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['due_date', 'payment_status']);
        });

        // Driver routes table indexes (if exists)
        if (Schema::hasTable('driver_routes')) {
            Schema::table('driver_routes', function (Blueprint $table) {
                $table->index(['driver_id']);
                $table->index(['route_id']);
                $table->index(['is_active']);
                $table->index(['activated_at']);
                $table->index(['driver_id', 'is_active']);
            });
        }

        // Bag issues table indexes (if exists)
        if (Schema::hasTable('bag_issues')) {
            Schema::table('bag_issues', function (Blueprint $table) {
                $table->index(['organization_id']);
                $table->index(['driver_id']);
                $table->index(['client_id']);
                $table->index(['is_verified']);
                $table->index(['distribution_timestamp']);
                $table->index(['verification_timestamp']);
                $table->index(['organization_id', 'is_verified']);
            });
        }
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['role']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['isActive']);
            $table->dropIndex(['role', 'organization_id']);
            $table->dropIndex(['role', 'isActive']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['route_id']);
            $table->dropIndex(['organization_id', 'user_id']);
        });

        Schema::table('driver_bags_allocations', function (Blueprint $table) {
            $table->dropIndex(['driver_id']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['driver_id', 'status']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['action']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['isActive']);
            $table->dropIndex(['organization_id', 'isActive']);
        });

        Schema::table('bags', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['account_number']);
            $table->dropIndex(['phone_number']);
            $table->dropIndex(['trans_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['trans_time']);
            $table->dropIndex(['client_id', 'status']);
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['organization_id', 'created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['status']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['invoice_number']);
            $table->dropIndex(['type']);
            $table->dropIndex(['client_id', 'payment_status']);
            $table->dropIndex(['organization_id', 'payment_status']);
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropIndex(['due_date', 'payment_status']);
        });

        if (Schema::hasTable('driver_routes')) {
            Schema::table('driver_routes', function (Blueprint $table) {
                $table->dropIndex(['driver_id']);
                $table->dropIndex(['route_id']);
                $table->dropIndex(['is_active']);
                $table->dropIndex(['activated_at']);
                $table->dropIndex(['driver_id', 'is_active']);
            });
        }

        if (Schema::hasTable('bag_issues')) {
            Schema::table('bag_issues', function (Blueprint $table) {
                $table->dropIndex(['organization_id']);
                $table->dropIndex(['driver_id']);
                $table->dropIndex(['client_id']);
                $table->dropIndex(['is_verified']);
                $table->dropIndex(['distribution_timestamp']);
                $table->dropIndex(['verification_timestamp']);
                $table->dropIndex(['organization_id', 'is_verified']);
            });
        }
    }
};