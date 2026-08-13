<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            // Links an SMS log row to the announcement blast that sent it.
            // Used to resume big blasts without sending the same customer twice.
            // No FK on purpose (SQLite cannot add FKs to existing tables).
            $table->unsignedBigInteger('announcement_id')->nullable()->after('customer_id');
            $table->index(['announcement_id', 'status'], 'sms_logs_announcement_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex('sms_logs_announcement_status_idx');
            $table->dropColumn('announcement_id');
        });
    }
};
