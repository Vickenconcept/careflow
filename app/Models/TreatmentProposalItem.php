<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\TreatmentProposalItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'treatment_proposal_id',
    'sort_order',
    'description',
    'quantity',
    'unit_cost',
    'currency',
])]
class TreatmentProposalItem extends Model
{
    /** @use HasFactory<TreatmentProposalItemFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * @return BelongsTo<TreatmentProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(TreatmentProposal::class, 'treatment_proposal_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }
}
