<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('show_ticker')->default(true)->after('is_active');
            $table->boolean('send_sms')->default(false)->after('show_ticker');
            // No DB-level FK here on purpose: SQLite cannot add foreign keys to
            // an existing table, and this migration must run on any database.
            $table->unsignedBigInteger('customer_id')->nullable()->after('created_by');
            $table->timestamp('scheduled_at')->nullable()->after('ends_at');
            $table->timestamp('sent_at')->nullable()->after('scheduled_at');
            $table->index(['customer_id'], 'announcements_customer_id_idx');
            $table->index(['send_sms', 'is_active', 'scheduled_at', 'sent_at'], 'announcements_sms_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_sms_due_idx');
            $table->dropIndex('announcements_customer_id_idx');
            $table->dropColumn(['show_ticker', 'send_sms', 'customer_id', 'scheduled_at', 'sent_at']);
        });
    }
};
