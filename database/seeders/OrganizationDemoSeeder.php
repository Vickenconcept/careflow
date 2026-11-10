<?php

namespace Database\Seeders;

use App\Demo\CareFlowDemoDataset;
use Illuminate\Database\Seeder;

class OrganizationDemoSeeder extends Seeder
{
    /**
     * Seed CareFlow International Medical Centre demo org, staff, and synthetic cases.
     */
    public function run(): void
    {
        $result = app(CareFlowDemoDataset::class)->seed();

        $this->command?->info(sprintf(
            'Demo org [%s] ready with %d synthetic cases. Staff password: %s',
            $result['team']->slug,
            $result['cases_created'],
            CareFlowDemoDataset::PASSWORD,
        ));
    }
}
