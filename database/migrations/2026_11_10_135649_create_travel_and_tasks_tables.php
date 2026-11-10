<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_arrangements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('status')->default('draft');
            $table->date('expected_arrival_date')->nullable();
            $table->date('expected_departure_date')->nullable();
            $table->text('flight_information')->nullable();
            $table->boolean('airport_pickup_required')->default(false);
            $table->text('airport_pickup_details')->nullable();
            $table->text('transfer_arrangements')->nullable();
            $table->text('accommodation')->nullable();
            $table->text('hotel_details')->nullable();
            $table->string('visa_assistance_status')->default('not_required');
            $table->text('special_administrative_requirements')->nullable();
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('patient_case_id');
            $table->index(['team_id', 'status']);
        });

        Schema::create('case_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['patient_case_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
            $table->index(['due_at']);
        });

        Schema::create('case_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['case_task_id', 'created_at']);
        });

        Schema::create('case_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('channel');
            $table->string('direction');
            $table->string('status');
            $table->string('language', 10)->default('en');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('sender')->nullable();
            $table->string('recipient')->nullable();
            $table->string('provider_reference')->nullable();
            $table->boolean('is_ai_draft')->default(false);
            $table->boolean('requires_approval')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('ai_metadata')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'channel']);
            $table->index(['patient_case_id', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_communications');
        Schema::dropIfExists('case_task_comments');
        Schema::dropIfExists('case_tasks');
        Schema::dropIfExists('travel_arrangements');
    }
};
