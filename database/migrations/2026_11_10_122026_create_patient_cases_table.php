<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('status');
            $table->string('priority')->default('normal');
            $table->foreignId('assigned_coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('portal_access_token_hash')->nullable()->index();
            $table->timestamp('portal_access_token_expires_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'assigned_coordinator_id']);
            $table->index(['team_id', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_cases');
    }
};
