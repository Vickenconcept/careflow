<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_enquiry_id')->constrained()->cascadeOnDelete();
            $table->uuid('submission_uuid');
            $table->string('consent_type');
            $table->string('privacy_notice_version');
            $table->string('consent_text_version');
            $table->boolean('accepted');
            $table->timestamp('consented_at');
            $table->string('source_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['team_id', 'patient_id']);
            $table->index(['submission_uuid', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
    }
};
