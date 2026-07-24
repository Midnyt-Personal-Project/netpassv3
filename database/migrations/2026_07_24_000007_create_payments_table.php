<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('GHS');
            $table->string('paystack_reference')->unique();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->decimal('platform_commission', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
