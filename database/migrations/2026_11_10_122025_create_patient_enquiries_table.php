<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('submission_uuid')->unique();
            $table->string('preferred_language', 10)->default('en');
            $table->string('reason_for_contact');
            $table->text('diagnosis')->nullable();
            $table->text('symptoms_or_history')->nullable();
            $table->text('previous_procedures')->nullable();
            $table->text('current_medications')->nullable();
            $table->text('known_allergies')->nullable();
            $table->text('additional_comments')->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['team_id', 'submitted_at']);
            $table->index(['team_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_enquiries');
    }
};
