<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_reference_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['team_id', 'year']);
        });

        Schema::create('treatment_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_physician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('clinical_recommendation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_proposal_id')->nullable()->constrained('treatment_proposals')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('status');
            $table->string('proposed_procedure');
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('expected_duration')->nullable();
            $table->unsignedInteger('required_appointments')->nullable();
            $table->text('additional_information')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamp('submitted_for_approval_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('patient_responded_at')->nullable();
            $table->string('patient_response')->nullable();
            $table->text('decision_notes')->nullable();
            $table->boolean('is_current')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['patient_case_id', 'version'], 'treatment_proposal_case_version_unique');
            $table->index(['team_id', 'status']);
            $table->index(['patient_case_id', 'is_current']);
        });

        Schema::create('treatment_proposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_proposal_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->index(['treatment_proposal_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_proposal_items');
        Schema::dropIfExists('treatment_proposals');
        Schema::dropIfExists('proposal_reference_sequences');
    }
};
