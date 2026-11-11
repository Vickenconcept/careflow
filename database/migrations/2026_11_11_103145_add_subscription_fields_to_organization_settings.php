<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('plan', 30)->default('trial')->after('document_retention_months');
            $table->string('subscription_status', 30)->default('active')->after('plan');
            $table->unsignedSmallInteger('seat_limit')->nullable()->after('subscription_status');
            $table->json('locked_modules')->nullable()->after('seat_limit');
            $table->text('platform_notes')->nullable()->after('locked_modules');
        });
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn(['plan', 'subscription_status', 'seat_limit', 'locked_modules', 'platform_notes']);
        });
    }
};
