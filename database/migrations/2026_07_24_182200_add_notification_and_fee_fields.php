<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('subscription_email_notifications')->default(false)->after('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('paystack_fee', 10, 2)->default(0)->after('platform_commission');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('paystack_fee');
        });
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('subscription_email_notifications');
        });
    }
};
