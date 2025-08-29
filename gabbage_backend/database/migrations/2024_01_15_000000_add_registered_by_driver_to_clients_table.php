<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('registered_by_driver')->nullable()->after('grace_period');
            $table->foreign('registered_by_driver')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['registered_by_driver']);
            $table->dropColumn('registered_by_driver');
        });
    }
};