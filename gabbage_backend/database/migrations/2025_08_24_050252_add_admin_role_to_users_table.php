<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support ENUM or MODIFY COLUMN
        // The role column should already exist from the users table creation
        // This migration is essentially a no-op for SQLite
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed for SQLite
    }
};
