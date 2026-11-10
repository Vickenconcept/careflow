<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_processing_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('sections');
            $table->json('corrected_sections')->nullable();
            $table->string('provider');
            $table->string('disclaimer');
            $table->boolean('is_synthetic')->default(true);
            $table->string('review_status')->default('unreviewed');
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['patient_document_id', 'version'], 'doc_summary_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_summaries');
    }
};
