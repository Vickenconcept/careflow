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
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('legal_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('default_currency', 3)->default('EUR');
            $table->string('default_language', 10)->default('en');
            $table->json('supported_languages')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('brand_primary_color', 20)->nullable();
            $table->string('brand_secondary_color', 20)->nullable();
            $table->boolean('ai_processing_enabled')->default(false);
            $table->string('ai_provider')->default('mock');
            $table->json('enabled_modules')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
