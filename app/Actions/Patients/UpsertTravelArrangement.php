<?php

namespace App\Actions\Patients;

use App\Enums\CaseActivityType;
use App\Enums\TravelArrangementStatus;
use App\Enums\VisaAssistanceStatus;
use App\Models\PatientCase;
use App\Models\TravelArrangement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpsertTravelArrangement
{
    public function __construct(
        private RecordCaseActivity $recordActivity,
    ) {}

    /**
     * @param  array{
     *     status?: string,
     *     assigned_coordinator_id?: int|null,
     *     expected_arrival_date?: string|null,
     *     expected_departure_date?: string|null,
     *     flight_information?: string|null,
     *     airport_pickup_required?: bool,
     *     airport_pickup_details?: string|null,
     *     transfer_arrangements?: string|null,
     *     accommodation?: string|null,
     *     hotel_details?: string|null,
     *     visa_assistance_status?: string,
     *     special_administrative_requirements?: string|null,
     *     checklist?: list<array{key: string, label: string, done: bool}>|null,
     *     notes?: string|null
     * }  $data
     */
    public function handle(PatientCase $case, User $actor, array $data): TravelArrangement
    {
        return DB::transaction(function () use ($case, $actor, $data) {
            /** @var PatientCase $locked */
            $locked = PatientCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();

            $coordinatorId = $data['assigned_coordinator_id'] ?? $locked->assigned_coordinator_id;
            if ($coordinatorId !== null) {
                $coordinator = User::query()->findOrFail($coordinatorId);
                if (! $coordinator->belongsToTeam($locked->team)) {
                    throw new InvalidArgumentException('Assigned coordinator must belong to this organization.');
                }
            }

            $existing = TravelArrangement::query()
                ->where('patient_case_id', $locked->id)
                ->lockForUpdate()
                ->first();

            $payload = [
                'team_id' => $locked->team_id,
                'patient_case_id' => $locked->id,
                'assigned_coordinator_id' => $coordinatorId,
                'updated_by_user_id' => $actor->id,
                'status' => TravelArrangementStatus::from($data['status'] ?? $existing?->status?->value ?? TravelArrangementStatus::Draft->value),
                'expected_arrival_date' => $data['expected_arrival_date'] ?? null,
                'expected_departure_date' => $data['expected_departure_date'] ?? null,
                'flight_information' => $data['flight_information'] ?? null,
                'airport_pickup_required' => (bool) ($data['airport_pickup_required'] ?? false),
                'airport_pickup_details' => $data['airport_pickup_details'] ?? null,
                'transfer_arrangements' => $data['transfer_arrangements'] ?? null,
                'accommodation' => $data['accommodation'] ?? null,
                'hotel_details' => $data['hotel_details'] ?? null,
                'visa_assistance_status' => VisaAssistanceStatus::from(
                    $data['visa_assistance_status'] ?? VisaAssistanceStatus::NotRequired->value,
                ),
                'special_administrative_requirements' => $data['special_administrative_requirements'] ?? null,
                'checklist' => $data['checklist'] ?? $existing?->checklist ?? $this->defaultChecklist(),
                'notes' => $data['notes'] ?? null,
                'is_demo' => $locked->is_demo,
            ];

            if ($existing === null) {
                $arrangement = TravelArrangement::query()->create([
                    ...$payload,
                    'uuid' => (string) Str::uuid(),
                ]);
                $summary = 'Travel arrangement created';
            } else {
                $existing->fill($payload)->save();
                $arrangement = $existing;
                $summary = 'Travel arrangement updated';
            }

            $locked->forceFill(['last_activity_at' => now()])->save();

            $this->recordActivity->handle(
                case: $locked,
                type: CaseActivityType::TravelArrangementUpdated,
                summary: $summary,
                actor: $actor,
                properties: [
                    'travel_uuid' => $arrangement->uuid,
                    'status' => $arrangement->status->value,
                ],
            );

            return $arrangement->fresh(['assignedCoordinator:id,name,email']);
        });
    }

    /**
     * @return list<array{key: string, label: string, done: bool}>
     */
    protected function defaultChecklist(): array
    {
        return [
            ['key' => 'arrival_confirmed', 'label' => 'Arrival date confirmed', 'done' => false],
            ['key' => 'transfer_booked', 'label' => 'Transfer arranged', 'done' => false],
            ['key' => 'accommodation_booked', 'label' => 'Accommodation arranged', 'done' => false],
            ['key' => 'visa_checked', 'label' => 'Visa assistance reviewed', 'done' => false],
        ];
    }
}
