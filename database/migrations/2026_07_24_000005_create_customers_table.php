<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->string('username')->unique(); // e.g. OY123456
            $table->string('password'); // e.g. 7890
            $table->string('phone_number')->nullable();
            $table->foreignId('active_package_id')->nullable()->constrained('packages')->onDelete('set null');
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['active', 'expired', 'suspended'])->default('active');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
};
