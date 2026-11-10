<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity_type');
            $table->string('summary');
            $table->json('properties')->nullable();
            $table->boolean('is_patient_visible')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['patient_case_id', 'occurred_at']);
            $table->index(['team_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_activities');
    }
};
