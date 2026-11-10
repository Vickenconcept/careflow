<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\TreatmentProposalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentProposalItem>
 */
class TreatmentProposalItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'treatment_proposal_id' => TreatmentProposal::factory(),
            'sort_order' => 1,
            'description' => 'Consultation package',
            'quantity' => 1,
            'unit_cost' => 250,
            'currency' => 'EUR',
        ];
    }
}
