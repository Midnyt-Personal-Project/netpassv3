<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->string('router_id')->unique(); // e.g. RTR-000001
            $table->string('api_token')->unique(); // e.g. oyalo_xxxxx
            $table->string('name');
            $table->string('model')->nullable();
            $table->timestamp('last_heartbeat')->nullable();
            $table->enum('status', ['online', 'offline'])->default('offline');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('routers');
    }
};
