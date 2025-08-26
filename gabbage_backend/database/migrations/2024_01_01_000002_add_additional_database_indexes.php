<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add indexes only if they don't exist
        $this->addIndexIfNotExists('payments', 'organization_id');
        $this->addIndexIfNotExists('payments', 'client_id');
        $this->addIndexIfNotExists('payments', 'account_number');
        $this->addIndexIfNotExists('payments', 'phone_number');
        $this->addIndexIfNotExists('payments', 'trans_id');
        $this->addIndexIfNotExists('payments', 'status');
        $this->addIndexIfNotExists('payments', 'payment_method');
        $this->addIndexIfNotExists('payments', 'created_at');
        $this->addIndexIfNotExists('payments', 'trans_time');
        $this->addCompositeIndexIfNotExists('payments', ['client_id', 'status']);
        $this->addCompositeIndexIfNotExists('payments', ['organization_id', 'status']);
        $this->addCompositeIndexIfNotExists('payments', ['organization_id', 'created_at']);

        $this->addIndexIfNotExists('invoices', 'organization_id');
        $this->addIndexIfNotExists('invoices', 'client_id');
        $this->addIndexIfNotExists('invoices', 'payment_status');
        $this->addIndexIfNotExists('invoices', 'status');
        $this->addIndexIfNotExists('invoices', 'due_date');
        $this->addIndexIfNotExists('invoices', 'created_at');
        $this->addIndexIfNotExists('invoices', 'invoice_number');
        $this->addIndexIfNotExists('invoices', 'type');
        $this->addCompositeIndexIfNotExists('invoices', ['client_id', 'payment_status']);
        $this->addCompositeIndexIfNotExists('invoices', ['organization_id', 'payment_status']);
        $this->addCompositeIndexIfNotExists('invoices', ['organization_id', 'created_at']);
        $this->addCompositeIndexIfNotExists('invoices', ['due_date', 'payment_status']);
    }

    private function addIndexIfNotExists($table, $column)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) return;
        
        $indexName = $table . '_' . $column . '_index';
        $exists = \DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
        
        if (empty($exists)) {
            Schema::table($table, function ($t) use ($column) {
                $t->index([$column]);
            });
        }
    }
    
    private function addCompositeIndexIfNotExists($table, $columns)
    {
        if (!Schema::hasTable($table)) return;
        
        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) return;
        }
        
        $indexName = $table . '_' . implode('_', $columns) . '_index';
        $exists = \DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
        
        if (empty($exists)) {
            Schema::table($table, function ($t) use ($columns) {
                $t->index($columns);
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                try { $table->dropIndex(['organization_id']); } catch (Exception $e) {}
                try { $table->dropIndex(['client_id']); } catch (Exception $e) {}
                try { $table->dropIndex(['account_number']); } catch (Exception $e) {}
                try { $table->dropIndex(['phone_number']); } catch (Exception $e) {}
                try { $table->dropIndex(['trans_id']); } catch (Exception $e) {}
                try { $table->dropIndex(['status']); } catch (Exception $e) {}
                try { $table->dropIndex(['payment_method']); } catch (Exception $e) {}
                try { $table->dropIndex(['created_at']); } catch (Exception $e) {}
                try { $table->dropIndex(['trans_time']); } catch (Exception $e) {}
                try { $table->dropIndex(['client_id', 'status']); } catch (Exception $e) {}
                try { $table->dropIndex(['organization_id', 'status']); } catch (Exception $e) {}
                try { $table->dropIndex(['organization_id', 'created_at']); } catch (Exception $e) {}
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                try { $table->dropIndex(['organization_id']); } catch (Exception $e) {}
                try { $table->dropIndex(['client_id']); } catch (Exception $e) {}
                try { $table->dropIndex(['payment_status']); } catch (Exception $e) {}
                try { $table->dropIndex(['status']); } catch (Exception $e) {}
                try { $table->dropIndex(['due_date']); } catch (Exception $e) {}
                try { $table->dropIndex(['created_at']); } catch (Exception $e) {}
                try { $table->dropIndex(['invoice_number']); } catch (Exception $e) {}
                try { $table->dropIndex(['type']); } catch (Exception $e) {}
                try { $table->dropIndex(['client_id', 'payment_status']); } catch (Exception $e) {}
                try { $table->dropIndex(['organization_id', 'payment_status']); } catch (Exception $e) {}
                try { $table->dropIndex(['organization_id', 'created_at']); } catch (Exception $e) {}
                try { $table->dropIndex(['due_date', 'payment_status']); } catch (Exception $e) {}
            });
        }

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