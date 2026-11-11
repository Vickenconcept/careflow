<?php

namespace Database\Factories;

use App\Models\DocumentAccessLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentAccessLog>
 */
class DocumentAccessLogFactory extends Factory
{
    protected $model = DocumentAccessLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'action' => 'view',
            'document_filename' => 'report.pdf',
            'case_reference' => 'CF-2026-000001',
            'ip_address' => '127.0.0.1',
            'accessed_at' => now(),
        ];
    }
}
