<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\ClinicalRecommendationStatus;
use App\Enums\TreatmentProposalStatus;
use App\Models\ClinicalRecommendation;
use App\Models\Department;
use App\Models\TreatmentProposal;
use App\Models\TreatmentProposalItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateTreatmentProposal
{
    public function __construct(private RecordCaseActivity $recordActivity) {}

    /**
     * @param  array{
     *     department_id?: int|null,
     *     responsible_physician_id?: int|null,
     *     clinical_recommendation_id?: int|null,
     *     proposed_procedure: string,
     *     estimated_cost?: string|float|null,
     *     currency?: string,
     *     expected_duration?: string|null,
     *     required_appointments?: int|null,
     *     additional_information?: string|null,
     *     valid_until?: string|null,
     *     items?: list<array{description: string, quantity?: int, unit_cost?: string|float|null, currency?: string}>
     * }  $data
     */
    public function handle(TreatmentProposal $proposal, User $actor, array $data): TreatmentProposal
    {
        return DB::transaction(function () use ($proposal, $actor, $data) {
            /** @var TreatmentProposal $locked */
            $locked = TreatmentProposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isEditable()) {
                throw new InvalidArgumentException('Only draft or awaiting-approval proposals can be edited.');
            }

            $case = $locked->case()->lockForUpdate()->firstOrFail();

            $recommendation = null;
            if (array_key_exists('clinical_recommendation_id', $data)) {
                $recommendationId = $data['clinical_recommendation_id'];
                if ($recommendationId !== null && $recommendationId !== '') {
                    $recommendation = ClinicalRecommendation::query()
                        ->whereKey($recommendationId)
                        ->where('patient_case_id', $case->id)
                        ->firstOrFail();

                    if ($recommendation->is_ai_generated || $recommendation->status !== ClinicalRecommendationStatus::Approved) {
                        throw new InvalidArgumentException('Only clinically approved recommendations can be linked to a proposal.');
                    }
                }
            } else {
                $recommendation = $locked->clinicalRecommendation;
            }

            if (array_key_exists('department_id', $data) && $data['department_id'] !== null) {
                $belongs = Department::query()
                    ->whereKey($data['department_id'])
                    ->where('team_id', $case->team_id)
                    ->exists();

                if (! $belongs) {
                    throw new InvalidArgumentException('Selected department does not belong to this organization.');
                }
            }

            if (! empty($data['responsible_physician_id'])) {
                $physician = User::query()->findOrFail($data['responsible_physician_id']);
                if (! $physician->belongsToTeam($case->team)) {
                    throw new InvalidArgumentException('Responsible physician must belong to this organization.');
                }
            }

            $locked->update([
                'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $locked->department_id,
                'responsible_physician_id' => array_key_exists('responsible_physician_id', $data)
                    ? $data['responsible_physician_id']
                    : $locked->responsible_physician_id,
                'clinical_recommendation_id' => array_key_exists('clinical_recommendation_id', $data)
                    ? $recommendation?->id
                    : $locked->clinical_recommendation_id,
                'proposed_procedure' => $data['proposed_procedure'],
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'currency' => strtoupper((string) ($data['currency'] ?? $locked->currency)),
                'expected_duration' => $data['expected_duration'] ?? null,
                'required_appointments' => $data['required_appointments'] ?? null,
                'additional_information' => $data['additional_information'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'status' => TreatmentProposalStatus::Draft,
                'submitted_for_approval_at' => null,
                'decision_notes' => null,
            ]);

            if (array_key_exists('items', $data)) {
                TreatmentProposalItem::query()->where('treatment_proposal_id', $locked->id)->delete();

                foreach (array_values($data['items'] ?? []) as $index => $item) {
                    TreatmentProposalItem::query()->create([
                        'team_id' => $locked->team_id,
                        'treatment_proposal_id' => $locked->id,
                        'sort_order' => $index + 1,
                        'description' => $item['description'],
                        'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                        'unit_cost' => $item['unit_cost'] ?? null,
                        'currency' => strtoupper((string) ($item['currency'] ?? $locked->currency)),
                    ]);
                }
            }

            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::TreatmentProposalUpdated,
                summary: sprintf('Treatment proposal %s updated', $locked->reference),
                actor: $actor,
                properties: [
                    'proposal_uuid' => $locked->uuid,
                    'proposal_reference' => $locked->reference,
                ],
            );

            return $locked->fresh(['items', 'department', 'responsiblePhysician:id,name,email', 'clinicalRecommendation']);
        });
    }
}
