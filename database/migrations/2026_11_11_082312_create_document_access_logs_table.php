<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_document_id')->nullable()->constrained('patient_documents')->nullOnDelete();
            $table->foreignId('patient_case_id')->nullable()->constrained('patient_cases')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->string('document_filename')->nullable();
            $table->string('case_reference', 40)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->index(['team_id', 'accessed_at']);
            $table->index(['patient_document_id', 'accessed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_access_logs');
    }
};
