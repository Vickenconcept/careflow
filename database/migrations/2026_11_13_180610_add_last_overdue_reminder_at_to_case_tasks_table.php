<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('case_tasks', function (Blueprint $table) {
            $table->timestamp('last_overdue_reminder_at')->nullable()->after('due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_tasks', function (Blueprint $table) {
            $table->dropColumn('last_overdue_reminder_at');
        });
    }
};
