<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('maintenance_mode')->default(false)->after('status');
            $table->text('maintenance_message')->nullable()->after('maintenance_mode');
        });
    }

    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['maintenance_mode', 'maintenance_message']);
        });
    }
};