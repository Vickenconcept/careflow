<?php

namespace App\Actions\Organizations;

use App\Enums\MessageTemplateKey;
use App\Enums\WorkflowKey;
use App\Models\Team;
use App\Models\WorkflowDefinition;
use Illuminate\Support\Str;

class EnsureDefaultWorkflowDefinitions
{
    public function handle(Team $team): void
    {
        foreach ($this->definitions() as $definition) {
            WorkflowDefinition::query()->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'key' => $definition['key']->value,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $definition['key']->label(),
                    'trigger' => $definition['key']->trigger(),
                    'is_enabled' => true,
                    'steps' => $definition['steps'],
                    'description' => $definition['description'],
                ],
            );
        }
    }

    /**
     * @return list<array{key: WorkflowKey, description: string, steps: list<array<string, mixed>>}>
     */
    protected function definitions(): array
    {
        return [
            [
                'key' => WorkflowKey::NewPatientEnquiry,
                'description' => 'Creates a coordinator task and drafts an enquiry-received confirmation for approval.',
                'steps' => [
                    [
                        'key' => 'create_coordinator_task',
                        'action' => 'create_task',
                        'title' => 'Review new patient enquiry',
                        'description' => 'Triage the new enquiry and assign next steps.',
                        'priority' => 'high',
                        'due_in_hours' => 24,
                        'assign_to' => 'coordinator',
                    ],
                    [
                        'key' => 'draft_enquiry_received',
                        'action' => 'draft_communication_from_template',
                        'template_key' => MessageTemplateKey::EnquiryReceived->value,
                        'requires_approval' => true,
                    ],
                    [
                        'key' => 'notify_coordinator',
                        'action' => 'notify_staff',
                        'audience' => 'coordinator',
                        'summary' => 'New patient enquiry requires coordinator review',
                    ],
                ],
            ],
            [
                'key' => WorkflowKey::MedicalDocumentsReceived,
                'description' => 'Notifies coordinator after AI document processing completes.',
                'steps' => [
                    [
                        'key' => 'create_review_task',
                        'action' => 'create_task',
                        'title' => 'Review AI document outputs',
                        'description' => 'Compare AI extraction against source documents before clinical handoff.',
                        'priority' => 'high',
                        'due_in_hours' => 12,
                        'assign_to' => 'coordinator',
                    ],
                    [
                        'key' => 'draft_documents_received',
                        'action' => 'draft_communication_from_template',
                        'template_key' => MessageTemplateKey::DocumentsReceived->value,
                        'requires_approval' => true,
                    ],
                    [
                        'key' => 'notify_coordinator',
                        'action' => 'notify_staff',
                        'audience' => 'coordinator',
                        'summary' => 'Document AI processing completed and awaits review',
                    ],
                ],
            ],
            [
                'key' => WorkflowKey::DoctorEvaluation,
                'description' => 'Notifies the assigned doctor and logs a review task.',
                'steps' => [
                    [
                        'key' => 'create_doctor_task',
                        'action' => 'create_task',
                        'title' => 'Complete clinical evaluation',
                        'description' => 'Review documents and record evaluation outcome.',
                        'priority' => 'high',
                        'due_in_hours' => 48,
                        'assign_to' => 'doctor',
                    ],
                    [
                        'key' => 'draft_assigned_for_review',
                        'action' => 'draft_communication_from_template',
                        'template_key' => MessageTemplateKey::CaseAssignedForReview->value,
                        'requires_approval' => true,
                    ],
                    [
                        'key' => 'notify_doctor',
                        'action' => 'notify_staff',
                        'audience' => 'doctor',
                        'summary' => 'Case assigned for doctor evaluation',
                    ],
                ],
            ],
            [
                'key' => WorkflowKey::AdditionalDocuments,
                'description' => 'Creates a follow-up task and drafts a patient request for additional documents.',
                'steps' => [
                    [
                        'key' => 'create_followup_task',
                        'action' => 'create_task',
                        'title' => 'Chase additional documents',
                        'description' => 'Follow up until requested documents are received.',
                        'priority' => 'high',
                        'due_in_hours' => 24,
                        'assign_to' => 'coordinator',
                    ],
                    [
                        'key' => 'draft_additional_documents',
                        'action' => 'draft_communication_from_template',
                        'template_key' => MessageTemplateKey::AdditionalDocumentsRequired->value,
                        'requires_approval' => true,
                    ],
                    [
                        'key' => 'notify_coordinator',
                        'action' => 'notify_staff',
                        'audience' => 'coordinator',
                        'summary' => 'Additional documents requested after clinical review',
                    ],
                ],
            ],
            [
                'key' => WorkflowKey::TreatmentCoordination,
                'description' => 'Creates appointment and travel tasks after proposal acceptance.',
                'steps' => [
                    [
                        'key' => 'create_appointment_task',
                        'action' => 'create_task',
                        'title' => 'Plan appointment with hospital scheduling',
                        'description' => 'Propose and confirm appointment through authoritative hospital source.',
                        'priority' => 'high',
                        'due_in_hours' => 48,
                        'assign_to' => 'coordinator',
                    ],
                    [
                        'key' => 'create_travel_task',
                        'action' => 'create_task',
                        'title' => 'Start travel coordination',
                        'description' => 'Capture arrival, transfer, and accommodation details (no passport numbers).',
                        'priority' => 'normal',
                        'due_in_hours' => 72,
                        'assign_to' => 'coordinator',
                    ],
                    [
                        'key' => 'notify_coordinator',
                        'action' => 'notify_staff',
                        'audience' => 'coordinator',
                        'summary' => 'Patient accepted proposal — appointment and travel tasks created',
                    ],
                ],
            ],
            [
                'key' => WorkflowKey::PostTreatmentFollowUp,
                'description' => 'Schedules a patient follow-up, creates a monitoring task, and drafts a reminder for approval.',
                'steps' => [
                    [
                        'key' => 'schedule_follow_up',
                        'action' => 'create_follow_up',
                        'title' => 'Post-treatment outcome review',
                        'description' => 'Confirm patient wellbeing and outstanding administrative needs.',
                        'follow_up_type' => 'outcome_review',
                        'due_in_days' => 7,
                    ],
                    [
                        'key' => 'create_followup_task',
                        'action' => 'create_task',
                        'title' => 'Monitor post-treatment follow-up',
                        'description' => 'Track follow-up completion and record patient communication.',
                        'priority' => 'normal',
                        'due_in_hours' => 168,
                        'assign_to' => 'coordinator',
                    ],
                    [
                        'key' => 'draft_followup_reminder',
                        'action' => 'draft_communication_from_template',
                        'template_key' => MessageTemplateKey::FollowUpReminder->value,
                        'requires_approval' => true,
                    ],
                ],
            ],
        ];
    }
}
