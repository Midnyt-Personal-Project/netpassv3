<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->string('name'); // e.g. 1 Hour, 1 Day
            $table->decimal('price', 10, 2); // e.g. 5.00 GHS
            $table->integer('duration_minutes'); // e.g. 60
            $table->string('speed_limit_up')->nullable(); // e.g. 2M
            $table->string('speed_limit_down')->nullable(); // e.g. 5M
            $table->integer('data_limit_mb')->nullable(); // Data cap in MB
            $table->integer('share_users')->default(1); // Allowed shared users
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('packages');
    }
};
