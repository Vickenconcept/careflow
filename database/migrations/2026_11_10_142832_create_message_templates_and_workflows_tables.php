<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('key');
            $table->string('name');
            $table->string('channel')->default('email');
            $table->boolean('is_active')->default(true);
            $table->json('subject_translations')->nullable();
            $table->json('body_translations');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'key']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('key');
            $table->string('name');
            $table->string('trigger');
            $table->boolean('is_enabled')->default(true);
            $table->json('steps');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'key']);
            $table->index(['team_id', 'trigger', 'is_enabled']);
        });

        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_definition_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('workflow_key');
            $table->string('idempotency_key');
            $table->string('status');
            $table->json('context')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'idempotency_key']);
            $table->index(['patient_case_id', 'workflow_key']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('workflow_execution_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_execution_id')->constrained()->cascadeOnDelete();
            $table->string('step_key');
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('action');
            $table->string('status');
            $table->json('config')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_execution_id', 'step_key']);
            $table->index(['workflow_execution_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_steps');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('workflow_definitions');
        Schema::dropIfExists('message_templates');
    }
};
