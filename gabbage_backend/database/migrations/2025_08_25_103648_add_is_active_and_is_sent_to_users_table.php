<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'isActive')) {
                $table->boolean('isActive')->default(true);
            }
            if (!Schema::hasColumn('users', 'isSent')) {
                $table->boolean('isSent')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'isActive')) {
                $table->dropColumn('isActive');
            }
            if (Schema::hasColumn('users', 'isSent')) {
                $table->dropColumn('isSent');
            }
        });
    }
};