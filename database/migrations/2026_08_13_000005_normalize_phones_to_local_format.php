<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Standardize every stored phone number on the local 0XXXXXXXXX format
     * (e.g. 233542069352 becomes 0542069352). Announcement deduplication and
     * recipient lookup rely on one consistent format.
     */
    public function up(): void
    {
        DB::table('customers')
            ->whereNotNull('phone_number')
            ->where('phone_number', 'like', '233%')
            ->select(['id', 'phone_number'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (preg_match('/^233[2-9][0-9]{8}$/', (string) $row->phone_number)) {
                        DB::table('customers')
                            ->where('id', $row->id)
                            ->update(['phone_number' => '0'.substr($row->phone_number, 3)]);
                    }
                }
            });

        DB::table('payments')
            ->whereNotNull('purchaser_phone')
            ->where('purchaser_phone', 'like', '233%')
            ->select(['id', 'purchaser_phone'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (preg_match('/^233[2-9][0-9]{8}$/', (string) $row->purchaser_phone)) {
                        DB::table('payments')
                            ->where('id', $row->id)
                            ->update(['purchaser_phone' => '0'.substr($row->purchaser_phone, 3)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Converting back would be lossy for numbers stored before the change,
        // so the down migration intentionally leaves the data as-is.
    }
};
