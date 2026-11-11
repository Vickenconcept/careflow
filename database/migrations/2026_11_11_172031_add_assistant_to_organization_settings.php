<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->boolean('assistant_enabled')->default(false);
            $table->string('assistant_name', 40)->default('Nora');
            $table->text('assistant_knowledge')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn(['assistant_enabled', 'assistant_name', 'assistant_knowledge']);
        });
    }
};
