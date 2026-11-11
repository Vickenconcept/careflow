<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * #2F6FED was the old platform default, saved on every hospital that opened
     * its settings. Clearing it lets those hospitals follow the new platform
     * colour until they deliberately choose their own.
     */
    public function up(): void
    {
        DB::table('organization_settings')
            ->whereRaw('UPPER(brand_primary_color) = ?', ['#2F6FED'])
            ->update(['brand_primary_color' => null]);
    }

    public function down(): void
    {
        // The original value cannot be told apart from a deliberate choice.
    }
};
