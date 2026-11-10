<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('status');
            $table->string('appointment_type');
            $table->timestamp('proposed_starts_at')->nullable();
            $table->timestamp('proposed_ends_at')->nullable();
            $table->timestamp('confirmed_starts_at')->nullable();
            $table->timestamp('confirmed_ends_at')->nullable();
            $table->string('location')->nullable();
            $table->text('meeting_instructions')->nullable();
            $table->string('external_his_reference')->nullable();
            $table->string('confirmation_source')->nullable();
            $table->boolean('hospital_slot_verified')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['patient_case_id', 'status']);
            $table->index(['patient_case_id', 'confirmed_starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_appointments');
    }
};
