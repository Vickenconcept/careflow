<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_processing_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('source_language', 10);
            $table->string('target_language', 10);
            $table->longText('translated_text');
            $table->string('provider');
            $table->string('status')->default('completed');
            $table->string('disclaimer');
            $table->boolean('is_synthetic')->default(true);
            $table->boolean('is_current')->default(true);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_outcome')->nullable();
            $table->timestamp('translated_at');
            $table->timestamps();

            $table->unique(['patient_document_id', 'version'], 'doc_translation_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_translations');
    }
};
