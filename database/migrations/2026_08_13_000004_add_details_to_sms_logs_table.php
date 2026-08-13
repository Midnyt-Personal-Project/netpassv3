<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            // What triggered this SMS: voucher, expiry, announcement, test, other.
            $table->string('type', 20)->default('other')->after('status');
            // How many number formats were tried before the final result.
            $table->unsignedTinyInteger('attempts')->default(1)->after('type');
            // The exact raw reply from the Arkesel gateway (includes balance on success).
            $table->text('gateway_response')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn(['type', 'attempts', 'gateway_response']);
        });
    }
};
