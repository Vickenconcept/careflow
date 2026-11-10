<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_extracted_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_processing_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('extracted_text');
            $table->string('provider');
            $table->boolean('is_synthetic')->default(true);
            $table->json('warnings')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['patient_document_id', 'version'], 'doc_extracted_text_version_unique');
        });

        Schema::create('document_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_processing_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('schema_version');
            $table->json('payload');
            $table->json('corrected_payload')->nullable();
            $table->string('provider');
            $table->boolean('is_synthetic')->default(true);
            $table->string('review_status')->default('unreviewed');
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['patient_document_id', 'version'], 'doc_extraction_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extractions');
        Schema::dropIfExists('document_extracted_texts');
    }
};
