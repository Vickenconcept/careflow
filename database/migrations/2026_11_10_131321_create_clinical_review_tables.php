<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('status');
            $table->text('findings')->nullable();
            $table->text('additional_information_request')->nullable();
            $table->string('outcome')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index(['patient_case_id', 'status']);
            $table->index(['team_id', 'reviewer_user_id', 'status']);
        });

        Schema::create('clinical_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_review_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('visibility')->default('internal');
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->index(['patient_case_id', 'observed_at']);
        });

        Schema::create('clinical_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_review_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_recommendation_id')->nullable()->constrained('clinical_recommendations')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('status');
            $table->text('recommendation_text');
            $table->json('supporting_document_uuids')->nullable();
            $table->boolean('is_ai_generated')->default(false);
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['patient_case_id', 'version'], 'clinical_recommendation_case_version_unique');
            $table->index(['patient_case_id', 'is_current']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('clinical_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_recommendation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('outcome');
            $table->text('notes')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['clinical_recommendation_id', 'decided_at']);
            $table->index(['team_id', 'approver_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_approvals');
        Schema::dropIfExists('clinical_recommendations');
        Schema::dropIfExists('clinical_observations');
        Schema::dropIfExists('doctor_reviews');
    }
};
