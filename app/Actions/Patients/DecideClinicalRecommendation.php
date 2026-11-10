<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\ClinicalApprovalOutcome;
use App\Enums\ClinicalRecommendationStatus;
use App\Models\ClinicalApproval;
use App\Models\ClinicalRecommendation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DecideClinicalRecommendation
{
    public function __construct(private RecordCaseActivity $recordActivity) {}

    public function handle(
        ClinicalRecommendation $recommendation,
        User $approver,
        ClinicalApprovalOutcome $outcome,
        ?string $notes = null,
    ): ClinicalApproval {
        return DB::transaction(function () use ($recommendation, $approver, $outcome, $notes) {
            /** @var ClinicalRecommendation $locked */
            $locked = ClinicalRecommendation::query()
                ->whereKey($recommendation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->is_ai_generated) {
                throw new InvalidArgumentException('AI-generated content cannot receive clinical approval.');
            }

            if ($locked->status !== ClinicalRecommendationStatus::AwaitingApproval) {
                throw new InvalidArgumentException('Only recommendations awaiting approval can be decided.');
            }

            $approval = ClinicalApproval::query()->create([
                'team_id' => $locked->team_id,
                'clinical_recommendation_id' => $locked->id,
                'approver_user_id' => $approver->id,
                'outcome' => $outcome,
                'notes' => $notes,
                'decided_at' => now(),
            ]);

            $locked->update([
                'status' => $outcome === ClinicalApprovalOutcome::Approved
                    ? ClinicalRecommendationStatus::Approved
                    : ClinicalRecommendationStatus::Rejected,
            ]);

            $case = $locked->case()->lockForUpdate()->firstOrFail();
            $case->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $case,
                type: CaseActivityType::ClinicalRecommendationDecided,
                summary: sprintf(
                    'Clinical recommendation %s (v%d)',
                    $outcome->label(),
                    $locked->version,
                ),
                actor: $approver,
                properties: [
                    'recommendation_uuid' => $locked->uuid,
                    'outcome' => $outcome->value,
                    'approval_id' => $approval->id,
                ],
            );

            return $approval->fresh(['approver:id,name,email']);
        });
    }
}
