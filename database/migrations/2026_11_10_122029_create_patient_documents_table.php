<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_case_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('original_filename');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('category');
            $table->string('processing_status');
            $table->string('original_language', 10)->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'patient_case_id']);
            $table->index(['patient_case_id', 'processing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_documents');
    }
};
