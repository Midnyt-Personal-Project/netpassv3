<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support ALTER COLUMN for enum types, so we rebuild the table.
        Schema::create('router_commands_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->enum('command_type', [
                'CREATE_USER',
                'REMOVE_USER',
                'DISABLE_USER',
                'ADD_MAC',
                'REMOVE_MAC',
                'CREATE_PROFILE',
                'REBOOT',
            ]);
            $table->json('payload');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });

        Schema::getConnection()->statement('INSERT INTO router_commands_new SELECT * FROM router_commands');
        Schema::drop('router_commands');
        Schema::rename('router_commands_new', 'router_commands');
    }

    public function down(): void
    {
        Schema::create('router_commands_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->enum('command_type', [
                'CREATE_USER',
                'REMOVE_USER',
                'DISABLE_USER',
                'ADD_MAC',
                'REMOVE_MAC',
                'CREATE_PROFILE',
            ]);
            $table->json('payload');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });

        Schema::getConnection()->statement("INSERT INTO router_commands_old SELECT * FROM router_commands WHERE command_type != 'REBOOT'");
        Schema::drop('router_commands');
        Schema::rename('router_commands_old', 'router_commands');
    }
};