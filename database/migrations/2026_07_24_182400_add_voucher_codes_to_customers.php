<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('voucher_code')->nullable()->unique()->after('password');
        });

        // Preserve existing access by turning the existing hotspot ID into a one-code voucher.
        DB::table('customers')->whereNull('voucher_code')->update([
            'voucher_code' => DB::raw('username'),
            'password' => DB::raw('username'),
        ]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['voucher_code']);
            $table->dropColumn('voucher_code');
        });
    }
};
