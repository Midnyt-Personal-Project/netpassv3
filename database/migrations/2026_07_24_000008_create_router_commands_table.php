<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('router_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->enum('command_type', ['CREATE_USER', 'REMOVE_USER', 'DISABLE_USER', 'ADD_MAC', 'REMOVE_MAC', 'CREATE_PROFILE']);
            $table->json('payload'); // Contains username, password, profile/limits, mac address etc.
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('router_commands');
    }
};
