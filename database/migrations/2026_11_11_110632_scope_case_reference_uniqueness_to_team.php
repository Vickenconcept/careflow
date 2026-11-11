<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Case and proposal references are generated per organization, so two hospitals
     * legitimately share the same number. Uniqueness must therefore be per team.
     */
    public function up(): void
    {
        Schema::table('patient_cases', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->unique(['team_id', 'reference']);
        });

        Schema::table('treatment_proposals', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->unique(['team_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('patient_cases', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'reference']);
            $table->unique('reference');
        });

        Schema::table('treatment_proposals', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'reference']);
            $table->unique('reference');
        });
    }
};
