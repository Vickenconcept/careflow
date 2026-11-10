<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\ClinicalRecommendationStatus;
use App\Enums\TreatmentProposalStatus;
use App\Models\ClinicalRecommendation;
use App\Models\Department;
use App\Models\PatientCase;
use App\Models\TreatmentProposal;
use App\Models\TreatmentProposalItem;
use App\Models\User;
use App\Support\ProposalReferenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateTreatmentProposal
{
    public function __construct(
        private ProposalReferenceGenerator $references,
        private RecordCaseActivity $recordActivity,
    ) {}

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
    public function handle(PatientCase $case, User $actor, array $data): TreatmentProposal
    {
        return DB::transaction(function () use ($case, $actor, $data) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();

            $recommendation = $this->resolveApprovedRecommendation(
                $locked,
                $data['clinical_recommendation_id'] ?? null,
            );

            $departmentId = $data['department_id'] ?? $locked->department_id;
            if ($departmentId !== null) {
                $belongs = Department::query()
                    ->whereKey($departmentId)
                    ->where('team_id', $locked->team_id)
                    ->exists();

                if (! $belongs) {
                    throw new InvalidArgumentException('Selected department does not belong to this organization.');
                }
            }

            $physicianId = $data['responsible_physician_id'] ?? $locked->assigned_doctor_id;
            if ($physicianId !== null) {
                $physician = User::query()->findOrFail($physicianId);
                if (! $physician->belongsToTeam($locked->team)) {
                    throw new InvalidArgumentException('Responsible physician must belong to this organization.');
                }
            }

            $nextVersion = ((int) TreatmentProposal::query()
                ->where('patient_case_id', $locked->id)
                ->max('version')) + 1;

            $proposal = TreatmentProposal::query()->create([
                'team_id' => $locked->team_id,
                'patient_case_id' => $locked->id,
                'department_id' => $departmentId,
                'responsible_physician_id' => $physicianId,
                'clinical_recommendation_id' => $recommendation?->id,
                'created_by_user_id' => $actor->id,
                'uuid' => (string) Str::uuid(),
                'reference' => $this->references->next($locked->team),
                'version' => max(1, $nextVersion),
                'status' => TreatmentProposalStatus::Draft,
                'proposed_procedure' => $data['proposed_procedure'],
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'currency' => strtoupper((string) ($data['currency'] ?? $locked->team->organizationSetting?->default_currency ?? 'EUR')),
                'expected_duration' => $data['expected_duration'] ?? null,
                'required_appointments' => $data['required_appointments'] ?? null,
                'additional_information' => $data['additional_information'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'is_current' => true,
                'is_demo' => $locked->is_demo,
            ]);

            $this->syncItems($proposal, $data['items'] ?? []);

            $locked->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $locked,
                type: CaseActivityType::TreatmentProposalCreated,
                summary: sprintf('Treatment proposal %s drafted', $proposal->reference),
                actor: $actor,
                properties: [
                    'proposal_uuid' => $proposal->uuid,
                    'proposal_reference' => $proposal->reference,
                    'version' => $proposal->version,
                ],
            );

            return $proposal->fresh(['items', 'department', 'responsiblePhysician:id,name,email', 'clinicalRecommendation']);
        });
    }

    protected function resolveApprovedRecommendation(PatientCase $case, mixed $recommendationId): ?ClinicalRecommendation
    {
        if ($recommendationId === null || $recommendationId === '') {
            return null;
        }

        $recommendation = ClinicalRecommendation::query()
            ->whereKey($recommendationId)
            ->where('patient_case_id', $case->id)
            ->firstOrFail();

        if ($recommendation->is_ai_generated) {
            throw new InvalidArgumentException('AI-generated content cannot be linked as a clinical recommendation.');
        }

        if ($recommendation->status !== ClinicalRecommendationStatus::Approved) {
            throw new InvalidArgumentException('Only clinically approved recommendations can be linked to a proposal.');
        }

        return $recommendation;
    }

    /**
     * @param  list<array{description: string, quantity?: int, unit_cost?: string|float|null, currency?: string}>  $items
     */
    protected function syncItems(TreatmentProposal $proposal, array $items): void
    {
        TreatmentProposalItem::query()
            ->where('treatment_proposal_id', $proposal->id)
            ->delete();

        foreach (array_values($items) as $index => $item) {
            TreatmentProposalItem::query()->create([
                'team_id' => $proposal->team_id,
                'treatment_proposal_id' => $proposal->id,
                'sort_order' => $index + 1,
                'description' => $item['description'],
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unit_cost' => $item['unit_cost'] ?? null,
                'currency' => strtoupper((string) ($item['currency'] ?? $proposal->currency)),
            ]);
        }
    }
}
