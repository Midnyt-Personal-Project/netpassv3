<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Persist checkout context so a Paystack callback does not depend
            // on a browser session that may be lost or overwritten.
            $table->string('purchaser_phone', 20)->nullable()->after('customer_id');
            $table->string('requested_mac_address', 17)->nullable()->after('purchaser_phone');
            $table->string('requested_device_name', 50)->nullable()->after('requested_mac_address');
            $table->timestamp('processed_at')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['processed_at']);
            $table->dropColumn([
                'purchaser_phone',
                'requested_mac_address',
                'requested_device_name',
                'processed_at',
            ]);
        });
    }
};
