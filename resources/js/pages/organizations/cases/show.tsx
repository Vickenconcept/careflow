import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    CalendarCheck,
    CalendarDays,
    Check,
    Circle,
    Download,
    Eye,
    FileText,
    Globe2,
    Info,
    Languages,
    LayoutDashboard,
    ListChecks,
    Mail,
    MessageSquare,
    NotebookPen,
    Phone,
    Plane,
    Pencil,
    Plus,
    ScrollText,
    Stethoscope,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { FormSheet } from '@/components/case/form-sheet';
import { StepTracker } from '@/components/case/step-tracker';
import { CaseConversation } from '@/components/case-conversation';
import type { ConversationMessage } from '@/components/case-conversation';
import InputError from '@/components/input-error';
import PageTabs from '@/components/page-tabs';
import { RowAction, RowActions } from '@/components/row-actions';
import { ToggleField } from '@/components/toggle-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

const UNASSIGNED = '__unassigned__';

type MemberOption = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
};

type Props = {
    team: { id: number; name: string; slug: string };
    case: {
        reference: string;
        status: string;
        status_label: string;
        priority_label: string;
        last_activity_at: string | null;
        is_demo: boolean;
        patient: {
            uuid: string;
            full_name: string;
            email: string;
            phone: string | null;
            date_of_birth: string | null;
            gender: string | null;
            country_of_residence: string | null;
            preferred_language: string;
        };
        enquiry: {
            reason_for_contact: string;
            diagnosis: string | null;
            symptoms_or_history: string | null;
            previous_procedures: string | null;
            current_medications: string | null;
            known_allergies: string | null;
            additional_comments: string | null;
            submitted_at: string;
            department: string | null;
        };
        assignments: {
            coordinator: { id: number; name: string; email: string } | null;
            doctor: { id: number; name: string; email: string } | null;
        };
        documents: Array<{
            uuid: string;
            original_filename: string;
            category: string;
            processing_status: string;
            ai_review: 'none' | 'unreviewed' | 'reviewed';
            size_bytes: number;
            uploaded_at: string;
            review_url: string;
            download_url: string | null;
            ai_state:
                | 'processing'
                | 'analysed'
                | 'unavailable'
                | 'not_analysed';
        }>;
        activities: Array<{
            id: number;
            type: string;
            type_label: string;
            summary: string;
            actor: string | null;
            occurred_at: string;
            is_patient_visible: boolean;
        }>;
        clinical: {
            disclaimer: string;
            reviews: Array<{
                uuid: string;
                status: string;
                status_label: string;
                outcome: string | null;
                outcome_label: string | null;
                findings: string | null;
                additional_information_request: string | null;
                reviewer: { id: number; name: string; email: string } | null;
                started_at: string;
                completed_at: string | null;
            }>;
            observations: Array<{
                id: number;
                body: string;
                visibility: string;
                author: string | null;
                observed_at: string;
            }>;
            recommendations: Array<{
                uuid: string;
                version: number;
                status: string;
                status_label: string;
                recommendation_text: string;
                supporting_document_uuids: string[];
                is_ai_generated: boolean;
                is_current: boolean;
                author: string | null;
                approvals: Array<{
                    id: number;
                    outcome: string;
                    outcome_label: string;
                    notes: string | null;
                    approver: string | null;
                    decided_at: string;
                }>;
            }>;
            urls: {
                store_observation: string;
                store_recommendation: string;
                store_review: string;
            };
            outcome_options: Array<{ value: string; label: string }>;
            approval_outcome_options: Array<{ value: string; label: string }>;
        } | null;
        proposals: {
            disclaimer: string;
            items: Array<{
                uuid: string;
                reference: string;
                version: number;
                status: string;
                status_label: string;
                proposed_procedure: string;
                estimated_cost: string | number | null;
                currency: string;
                expected_duration: string | null;
                required_appointments: number | null;
                additional_information: string | null;
                valid_until: string | null;
                is_current: boolean;
                department: string | null;
                responsible_physician: {
                    id: number;
                    name: string;
                    email: string;
                } | null;
                clinical_recommendation: {
                    id: number;
                    uuid: string;
                    version: number;
                    status: string;
                } | null;
                created_by: string | null;
                approved_by: string | null;
                approved_at: string | null;
                sent_at: string | null;
                patient_response: string | null;
                line_items: Array<{
                    id: number;
                    description: string;
                    quantity: number;
                    unit_cost: string | number | null;
                    currency: string;
                }>;
                transition_url: string;
            }>;
            approved_recommendations: Array<{
                id: number;
                uuid: string;
                version: number;
                label: string;
            }>;
            urls: { store: string };
            defaults: {
                department_id: number | null;
                responsible_physician_id: number | null;
                currency: string;
            };
        } | null;
        appointments: {
            disclaimer: string;
            items: Array<{
                uuid: string;
                status: string;
                status_label: string;
                appointment_type: string;
                appointment_type_label: string;
                proposed_starts_at: string | null;
                proposed_ends_at: string | null;
                confirmed_starts_at: string | null;
                confirmed_ends_at: string | null;
                location: string | null;
                meeting_instructions: string | null;
                external_his_reference: string | null;
                confirmation_source: string | null;
                confirmation_source_label: string | null;
                hospital_slot_verified: boolean;
                confirmed_at: string | null;
                notes: string | null;
                department: string | null;
                doctor: { id: number; name: string; email: string } | null;
                created_by: string | null;
                confirmed_by: string | null;
                transition_url: string;
            }>;
            type_options: Array<{ value: string; label: string }>;
            confirmation_source_options: Array<{
                value: string;
                label: string;
            }>;
            urls: { store: string };
            defaults: {
                department_id: number | null;
                doctor_id: number | null;
            };
        } | null;
        travel: {
            disclaimer: string;
            arrangement: {
                uuid: string;
                status: string;
                status_label: string;
                expected_arrival_date: string | null;
                expected_departure_date: string | null;
                flight_information: string | null;
                airport_pickup_required: boolean;
                airport_pickup_details: string | null;
                transfer_arrangements: string | null;
                accommodation: string | null;
                hotel_details: string | null;
                visa_assistance_status: string;
                visa_assistance_status_label: string;
                special_administrative_requirements: string | null;
                checklist: Array<{ key: string; label: string; done: boolean }>;
                notes: string | null;
                assigned_coordinator: {
                    id: number;
                    name: string;
                    email: string;
                } | null;
            } | null;
            status_options: Array<{ value: string; label: string }>;
            visa_options: Array<{ value: string; label: string }>;
            urls: { upsert: string };
        } | null;
        tasks: {
            items: Array<{
                uuid: string;
                title: string;
                description: string | null;
                status: string;
                status_label: string;
                priority: string;
                priority_label: string;
                due_at: string | null;
                completed_at: string | null;
                assigned_user: {
                    id: number;
                    name: string;
                    email: string;
                } | null;
                created_by: string | null;
                transition_url: string;
            }>;
            status_options: Array<{ value: string; label: string }>;
            priority_options: Array<{ value: string; label: string }>;
            urls: { store: string };
        } | null;
        follow_ups: {
            items: Array<{
                uuid: string;
                title: string;
                description: string | null;
                follow_up_type: string;
                follow_up_type_label: string;
                status: string;
                status_label: string;
                due_at: string | null;
                completed_at: string | null;
                outcome_notes: string | null;
                is_overdue: boolean;
                assigned_user: {
                    id: number;
                    name: string;
                    email: string;
                } | null;
                created_by: string | null;
                completed_by: string | null;
                update_url: string;
                complete_url: string;
            }>;
            type_options: Array<{ value: string; label: string }>;
            status_options: Array<{ value: string; label: string }>;
            urls: { store: string };
        } | null;
        communications: {
            disclaimer: string;
            items: ConversationMessage[];
            channel_options: Array<{ value: string; label: string }>;
            patient_email: string | null;
            ai_drafting_available: boolean;
            urls: { store: string };
        } | null;
    };
    availableStatusTransitions: Array<{ value: string; label: string }>;
    assignableMembers: {
        coordinators: MemberOption[];
        doctors: MemberOption[];
        staff: MemberOption[];
    };
    permissions: {
        canManageCases: boolean;
        canAssignCases: boolean;
        canUpdateStatus: boolean;
        canViewClinical: boolean;
        canReviewClinical: boolean;
        canApproveClinical: boolean;
        canManageProposals: boolean;
        canApproveProposals: boolean;
        canManageAppointments: boolean;
        canManageTravel: boolean;
        canManageTasks: boolean;
        canManageCommunications: boolean;
    };
};

type CaseTab =
    | 'overview'
    | 'documents'
    | 'evaluation'
    | 'communications'
    | 'proposal'
    | 'visit'
    | 'tasks';

type CasePanel =
    | 'review'
    | 'observation'
    | 'recommendation'
    | 'proposal'
    | 'appointment'
    | 'travel'
    | 'task'
    | 'follow_up';

/** Older links used separate tabs that now live inside another one. */
const TAB_ALIASES: Record<string, CaseTab> = {
    travel: 'visit',
    appointments: 'visit',
    timeline: 'overview',
    clinical: 'evaluation',
};

const TAB_ICONS: Record<CaseTab, LucideIcon> = {
    overview: LayoutDashboard,
    documents: FileText,
    evaluation: Stethoscope,
    communications: MessageSquare,
    proposal: ScrollText,
    visit: Plane,
    tasks: ListChecks,
};

const REVIEW_OUTCOME_LABELS: Record<string, string> = {
    ready_for_proposal: 'Ready for a treatment proposal',
    need_more_information: 'Needs more information from the patient',
};

const RECOMMENDATION_STATUS_LABELS: Record<string, string> = {
    draft: 'Draft',
    awaiting_approval: 'Waiting for approval',
    approved: 'Approved',
    rejected: 'Rejected',
    superseded: 'Replaced',
};

const PROPOSAL_STEPS = [
    { key: 'draft', label: 'Draft' },
    { key: 'awaiting_clinical_approval', label: 'Doctor approval' },
    { key: 'approved', label: 'Approved' },
    { key: 'sent_to_patient', label: 'With patient' },
    { key: 'patient_accepted', label: 'Accepted' },
];

const APPOINTMENT_STEPS = [
    { key: 'proposed', label: 'Proposed' },
    { key: 'awaiting_confirmation', label: 'Checking with hospital' },
    { key: 'confirmed', label: 'Confirmed' },
    { key: 'completed', label: 'Completed' },
];

const TRAVEL_STEPS = [
    { key: 'draft', label: 'Not started' },
    { key: 'in_progress', label: 'Arranging' },
    { key: 'confirmed', label: 'Confirmed' },
    { key: 'completed', label: 'Completed' },
];

const NEXT_STEPS: Record<string, { text: string; tab: CaseTab; cta: string }> =
    {
        new_enquiry: {
            text: 'Review the enquiry, assign a coordinator and request medical documents.',
            tab: 'overview',
            cta: 'Review enquiry',
        },
        awaiting_documents: {
            text: 'Waiting for the patient to upload documents. Follow up if they are late.',
            tab: 'communications',
            cta: 'Contact patient',
        },
        documents_received: {
            text: 'Documents received. Check that each one has been processed.',
            tab: 'documents',
            cta: 'Open documents',
        },
        ai_processing: {
            text: 'Documents are being processed. AI results are drafts until reviewed.',
            tab: 'documents',
            cta: 'View processing',
        },
        awaiting_coordinator_review: {
            text: 'Review the extracted information, then send the case to a doctor.',
            tab: 'documents',
            cta: 'Review documents',
        },
        awaiting_doctor_review: {
            text: 'Doctor evaluation is pending. Assign a doctor if none is set.',
            tab: 'evaluation',
            cta: 'Open evaluation',
        },
        additional_information_required: {
            text: 'More information is needed from the patient. Send the request.',
            tab: 'communications',
            cta: 'Contact patient',
        },
        treatment_proposal_preparation: {
            text: 'Prepare the treatment proposal for the patient.',
            tab: 'proposal',
            cta: 'Open proposal',
        },
        proposal_sent: {
            text: 'Proposal sent. The patient can accept or decline it on their case page.',
            tab: 'proposal',
            cta: 'View proposal',
        },
        awaiting_patient_response: {
            text: 'Waiting for the patient to respond. Follow up if needed.',
            tab: 'communications',
            cta: 'Contact patient',
        },
        appointment_planning: {
            text: 'Propose an appointment and confirm it with the hospital.',
            tab: 'visit',
            cta: 'Plan appointment',
        },
        travel_coordination: {
            text: "Arrange the patient's arrival, pickup and accommodation.",
            tab: 'visit',
            cta: 'Open visit',
        },
        treatment_scheduled: {
            text: 'Treatment is scheduled. Confirm travel arrangements are complete.',
            tab: 'visit',
            cta: 'Check travel',
        },
        treatment_completed: {
            text: 'Treatment completed. Schedule follow-up actions.',
            tab: 'tasks',
            cta: 'Plan follow-up',
        },
        follow_up: {
            text: 'Patient is in follow-up. Complete outstanding follow-up actions.',
            tab: 'tasks',
            cta: 'Open follow-ups',
        },
    };

export default function CaseShow({
    team,
    case: patientCase,
    availableStatusTransitions,
    assignableMembers,
    permissions,
}: Props) {
    const statusForm = useForm({
        status: availableStatusTransitions[0]?.value ?? '',
        note: '',
    });

    const assignmentForm = useForm({
        coordinator_id: patientCase.assignments.coordinator?.id
            ? String(patientCase.assignments.coordinator.id)
            : '',
        doctor_id: patientCase.assignments.doctor?.id
            ? String(patientCase.assignments.doctor.id)
            : '',
        clear_coordinator: false,
        clear_doctor: false,
    });

    const observationForm = useForm({
        body: '',
        visibility: 'internal',
    });

    const recommendationForm = useForm({
        recommendation_text: '',
        supporting_document_uuids: [] as string[],
        submit_for_approval: false,
    });

    const reviewForm = useForm({
        outcome: 'ready_for_proposal',
        findings: '',
        additional_information_request: '',
    });

    const approvalForm = useForm({
        outcome: 'approved',
        notes: '',
    });

    const proposalForm = useForm({
        department_id: patientCase.proposals?.defaults.department_id
            ? String(patientCase.proposals.defaults.department_id)
            : '',
        responsible_physician_id: patientCase.proposals?.defaults
            .responsible_physician_id
            ? String(patientCase.proposals.defaults.responsible_physician_id)
            : '',
        clinical_recommendation_id: '',
        proposed_procedure: '',
        estimated_cost: '',
        currency: patientCase.proposals?.defaults.currency ?? 'EUR',
        expected_duration: '',
        required_appointments: '',
        additional_information: '',
        valid_until: '',
        items: [
            {
                description: '',
                quantity: '1',
                unit_cost: '',
            },
        ],
    });

    const proposalTransitionForm = useForm({
        action: 'submit_for_approval',
        notes: '',
        patient_response: 'accepted',
    });

    /** Which side panel is open: adding or editing one kind of record at a time. */
    const [panel, setPanel] = useState<CasePanel | null>(null);

    const runProposalAction = (
        url: string,
        action: string,
        extra: { notes?: string; patient_response?: string } = {},
    ): void => {
        proposalTransitionForm.transform(() => ({
            action,
            notes: extra.notes ?? '',
            patient_response: extra.patient_response ?? 'accepted',
        }));
        proposalTransitionForm.post(url, { preserveScroll: true });
    };

    const appointmentForm = useForm({
        department_id: patientCase.appointments?.defaults.department_id
            ? String(patientCase.appointments.defaults.department_id)
            : '',
        doctor_id: patientCase.appointments?.defaults.doctor_id
            ? String(patientCase.appointments.defaults.doctor_id)
            : '',
        appointment_type: 'consultation',
        proposed_starts_at: '',
        proposed_ends_at: '',
        location: '',
        meeting_instructions: '',
        notes: '',
        external_his_reference: '',
    });

    const appointmentTransitionForm = useForm({
        action: 'request_confirmation',
        confirmation_source: 'manual_hospital_verified',
        hospital_slot_verified: true,
        confirmed_starts_at: '',
        confirmed_ends_at: '',
        external_his_reference: '',
        proposed_starts_at: '',
        proposed_ends_at: '',
        notes: '',
    });

    const runAppointmentAction = (
        appointment: {
            transition_url: string;
            external_his_reference: string | null;
        },
        action: string,
        extra: {
            hospital_slot_verified?: boolean;
            confirmed_starts_at?: string;
            confirmed_ends_at?: string;
            notes?: string;
        } = {},
    ): void => {
        appointmentTransitionForm.transform(() => ({
            action,
            confirmation_source: 'manual_hospital_verified',
            hospital_slot_verified: extra.hospital_slot_verified ?? false,
            confirmed_starts_at: extra.confirmed_starts_at ?? '',
            confirmed_ends_at: extra.confirmed_ends_at ?? '',
            external_his_reference: appointment.external_his_reference ?? '',
            proposed_starts_at: '',
            proposed_ends_at: '',
            notes: extra.notes ?? '',
        }));
        appointmentTransitionForm.post(appointment.transition_url, {
            preserveScroll: true,
        });
    };

    const travelForm = useForm({
        status: patientCase.travel?.arrangement?.status ?? 'draft',
        assigned_coordinator_id: patientCase.travel?.arrangement
            ?.assigned_coordinator?.id
            ? String(patientCase.travel.arrangement.assigned_coordinator.id)
            : patientCase.assignments.coordinator?.id
              ? String(patientCase.assignments.coordinator.id)
              : '',
        expected_arrival_date:
            patientCase.travel?.arrangement?.expected_arrival_date ?? '',
        expected_departure_date:
            patientCase.travel?.arrangement?.expected_departure_date ?? '',
        flight_information:
            patientCase.travel?.arrangement?.flight_information ?? '',
        airport_pickup_required:
            patientCase.travel?.arrangement?.airport_pickup_required ?? false,
        airport_pickup_details:
            patientCase.travel?.arrangement?.airport_pickup_details ?? '',
        transfer_arrangements:
            patientCase.travel?.arrangement?.transfer_arrangements ?? '',
        accommodation: patientCase.travel?.arrangement?.accommodation ?? '',
        hotel_details: patientCase.travel?.arrangement?.hotel_details ?? '',
        visa_assistance_status:
            patientCase.travel?.arrangement?.visa_assistance_status ??
            'not_required',
        special_administrative_requirements:
            patientCase.travel?.arrangement
                ?.special_administrative_requirements ?? '',
        notes: patientCase.travel?.arrangement?.notes ?? '',
    });

    const taskForm = useForm({
        title: '',
        description: '',
        assigned_user_id: '',
        priority: 'normal',
        due_at: '',
    });

    const taskTransitionForm = useForm({
        status: 'completed',
        comment: '',
    });

    const runFollowUpComplete = (url: string, cancel: boolean): void => {
        followUpCompleteForm.transform(() => ({
            outcome_notes: cancel
                ? 'Cancelled from case workspace'
                : 'Completed from case workspace',
            cancel,
        }));
        followUpCompleteForm.post(url, { preserveScroll: true });
    };

    const runTaskTransition = (url: string, status: string): void => {
        taskTransitionForm.transform(() => ({ status, comment: '' }));
        taskTransitionForm.post(url, { preserveScroll: true });
    };

    const followUpForm = useForm({
        title: '',
        description: '',
        follow_up_type: 'administrative',
        due_at: '',
        assigned_user_id: '',
    });

    const followUpCompleteForm = useForm({
        outcome_notes: '',
        cancel: false,
    });

    const clinical = patientCase.clinical;
    const proposals = patientCase.proposals;
    const appointments = patientCase.appointments;
    const travel = patientCase.travel;
    const tasks = patientCase.tasks;
    const followUps = patientCase.follow_ups;
    const communications = patientCase.communications;

    const patientInitials = patientCase.patient.full_name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');

    const unreviewedDocuments = patientCase.documents.filter(
        (item) => item.ai_review === 'unreviewed',
    );
    const unreviewedDocumentCount = unreviewedDocuments.length;
    const firstUnreviewedDocument = unreviewedDocuments[0] ?? null;

    const documentsReviewed =
        patientCase.documents.length > 0 && unreviewedDocumentCount === 0;
    const readyForDoctor =
        documentsReviewed && Boolean(patientCase.assignments.doctor);
    const canSendToDoctor = availableStatusTransitions.some(
        (item) => item.value === 'awaiting_doctor_review',
    );
    const canRequestMoreInfo = availableStatusTransitions.some(
        (item) => item.value === 'additional_information_required',
    );

    const openTaskCount =
        (tasks?.items.filter(
            (task) => !['completed', 'cancelled'].includes(task.status),
        ).length ?? 0) +
        (followUps?.items.filter(
            (item) => !['completed', 'cancelled'].includes(item.status),
        ).length ?? 0);

    const currentProposal =
        proposals?.items.find((item) => item.is_current) ?? null;
    const proposalNeedsAction =
        (currentProposal?.status === 'draft' &&
            permissions.canManageProposals) ||
        (currentProposal?.status === 'awaiting_clinical_approval' &&
            permissions.canApproveProposals) ||
        (currentProposal?.status === 'approved' &&
            permissions.canManageProposals);
    const visitNeedsAction =
        (appointments?.items.some((item) =>
            ['proposed', 'awaiting_confirmation'].includes(item.status),
        ) ??
            false) ||
        (currentProposal?.status === 'patient_accepted' &&
            (appointments?.items.every((item) => item.status === 'cancelled') ??
                false));
    const messagesNeedAction =
        communications?.items.some((item) =>
            ['draft', 'awaiting_approval'].includes(item.status),
        ) ?? false;
    const evaluationNeedsAction =
        (clinical?.recommendations.some(
            (item) => item.status === 'awaiting_approval',
        ) ??
            false) &&
        permissions.canApproveClinical;
    const tasksNeedAction =
        (followUps?.items.some((item) => item.is_overdue) ?? false) ||
        (tasks?.items.some(
            (task) =>
                task.due_at !== null &&
                new Date(task.due_at) < new Date() &&
                !['completed', 'cancelled'].includes(task.status),
        ) ??
            false);

    const visibleTabs: Array<{
        id: CaseTab;
        label: string;
        count?: number;
        attention?: string;
    }> = [
        { id: 'overview', label: 'Overview' },
        {
            id: 'documents',
            label: 'Documents',
            count: patientCase.documents.length,
            attention:
                unreviewedDocumentCount > 0
                    ? `${unreviewedDocumentCount} to review`
                    : undefined,
        },
        ...(permissions.canViewClinical && clinical
            ? [
                  {
                      id: 'evaluation' as CaseTab,
                      label: 'Evaluation',
                      attention: evaluationNeedsAction
                          ? 'Waiting for your approval'
                          : undefined,
                  },
              ]
            : []),
        ...(communications
            ? [
                  {
                      id: 'communications' as CaseTab,
                      label: 'Messages',
                      count: communications.items.length,
                      attention: messagesNeedAction
                          ? 'Drafts to approve'
                          : undefined,
                  },
              ]
            : []),
        ...(proposals
            ? [
                  {
                      id: 'proposal' as CaseTab,
                      label: 'Proposal',
                      attention: proposalNeedsAction
                          ? 'Needs your action'
                          : undefined,
                  },
              ]
            : []),
        ...(travel || appointments
            ? [
                  {
                      id: 'visit' as CaseTab,
                      label: 'Visit',
                      attention: visitNeedsAction
                          ? 'Appointment to confirm'
                          : undefined,
                  },
              ]
            : []),
        ...(tasks || followUps
            ? [
                  {
                      id: 'tasks' as CaseTab,
                      label: 'Tasks',
                      count: openTaskCount,
                      attention: tasksNeedAction ? 'Overdue' : undefined,
                  },
              ]
            : []),
    ];

    const [selectedTab, setSelectedTab] = useState<string>(() => {
        if (typeof window === 'undefined') {
            return 'overview';
        }

        const requested =
            new URLSearchParams(window.location.search).get('tab') ??
            'overview';

        return TAB_ALIASES[requested] ?? requested;
    });
    const tab: CaseTab =
        visibleTabs.find((item) => item.id === selectedTab)?.id ?? 'overview';

    const selectTab = (next: CaseTab): void => {
        setSelectedTab(next);

        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', next);
            window.history.replaceState(window.history.state, '', url);
        }
    };

    // Saving a form redirects back to the plain case URL; keep the open tab in
    // the address so a refresh or a shared link lands on the same tab.
    useEffect(() => {
        return router.on('navigate', () => {
            const url = new URL(window.location.href);

            if (url.searchParams.get('tab') !== tab) {
                url.searchParams.set('tab', tab);
                window.history.replaceState(window.history.state, '', url);
            }
        });
    }, [tab]);

    const baseNextStep = NEXT_STEPS[patientCase.status] ?? null;
    const nextStep =
        baseNextStep &&
        !visibleTabs.some((item) => item.id === baseNextStep.tab)
            ? {
                  ...baseNextStep,
                  tab: 'overview' as CaseTab,
                  cta: 'Open overview',
              }
            : baseNextStep;

    return (
        <>
            <Head title={patientCase.reference} />
            <div className="space-y-5 p-4 md:p-6">
                <section className="light-surface rounded-2xl border border-slate-200/80 bg-white px-5 py-5 shadow-[0_12px_40px_-28px_rgba(15,23,42,0.35)] md:px-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="flex min-w-0 items-start gap-3">
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand/10 text-sm font-semibold text-brand">
                                {patientInitials || (
                                    <UserRound className="size-5" />
                                )}
                            </span>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="truncate text-xl font-semibold tracking-tight text-slate-900">
                                        {patientCase.patient.full_name}
                                    </h1>
                                    <span className="rounded-full bg-brand/10 px-2.5 py-0.5 text-xs font-semibold text-brand">
                                        {patientCase.status_label}
                                    </span>
                                    <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                                        {patientCase.priority_label}
                                    </span>
                                    {patientCase.is_demo ? (
                                        <span className="rounded-full border border-slate-300 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                                            Demo
                                        </span>
                                    ) : null}
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    <span className="font-mono text-brand">
                                        {patientCase.reference}
                                    </span>
                                    {patientCase.patient.country_of_residence
                                        ? ` · ${patientCase.patient.country_of_residence}`
                                        : ''}
                                    {patientCase.patient.preferred_language
                                        ? ` · ${patientCase.patient.preferred_language}`
                                        : ''}
                                    {patientCase.enquiry.department
                                        ? ` · ${patientCase.enquiry.department}`
                                        : ''}
                                </p>
                                {patientCase.last_activity_at ? (
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        Updated{' '}
                                        {new Date(
                                            patientCase.last_activity_at,
                                        ).toLocaleString()}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                        <dl className="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                            <div>
                                <dt className="text-xs font-medium text-slate-500">
                                    Coordinator
                                </dt>
                                <dd className="font-medium text-slate-900">
                                    {patientCase.assignments.coordinator
                                        ?.name ?? (
                                        <span className="text-amber-700">
                                            Unassigned
                                        </span>
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-medium text-slate-500">
                                    Doctor
                                </dt>
                                <dd className="font-medium text-slate-900">
                                    {patientCase.assignments.doctor?.name ?? (
                                        <span className="text-amber-700">
                                            Unassigned
                                        </span>
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {nextStep ? (
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand/20 bg-brand/5 px-4 py-3">
                            <div className="flex min-w-0 items-start gap-2.5">
                                <ArrowRight className="mt-0.5 size-4 shrink-0 text-brand" />
                                <div className="min-w-0">
                                    <p className="text-xs font-semibold tracking-wide text-brand uppercase">
                                        Next step
                                    </p>
                                    <p className="text-sm text-slate-900">
                                        {nextStep.text}
                                    </p>
                                </div>
                            </div>
                            {nextStep.tab !== tab ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="rounded-full bg-brand text-white hover:bg-brand/90"
                                    onClick={() => selectTab(nextStep.tab)}
                                >
                                    {nextStep.cta}
                                </Button>
                            ) : null}
                        </div>
                    ) : null}

                    <PageTabs
                        className="mt-5"
                        surface="light"
                        label="Case sections"
                        idPrefix="case-tab"
                        panelId="case-tab-panel"
                        active={tab}
                        onSelect={(id) => selectTab(id as CaseTab)}
                        tabs={visibleTabs.map((item) => ({
                            ...item,
                            icon: TAB_ICONS[item.id],
                        }))}
                    />
                </section>

                <div
                    id="case-tab-panel"
                    role="tabpanel"
                    aria-labelledby={`case-tab-${tab}`}
                    className="space-y-5"
                >
                    {tab === 'overview' && (
                        <>
                            {(permissions.canUpdateStatus ||
                                permissions.canAssignCases) && (
                                <section className="grid gap-4 lg:grid-cols-2">
                                    {permissions.canUpdateStatus ? (
                                        <Card
                                            title="Update status"
                                            icon={Activity}
                                        >
                                            <form
                                                className="space-y-4"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    statusForm.patch(
                                                        `/${team.slug}/cases/${patientCase.reference}/status`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }}
                                            >
                                                <Field label="Next status">
                                                    <Select
                                                        value={
                                                            statusForm.data
                                                                .status ||
                                                            undefined
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            statusForm.setData(
                                                                'status',
                                                                value,
                                                            )
                                                        }
                                                        disabled={
                                                            availableStatusTransitions.length ===
                                                            0
                                                        }
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Select status" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {availableStatusTransitions.map(
                                                                (status) => (
                                                                    <SelectItem
                                                                        key={
                                                                            status.value
                                                                        }
                                                                        value={
                                                                            status.value
                                                                        }
                                                                    >
                                                                        {
                                                                            status.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        message={
                                                            statusForm.errors
                                                                .status
                                                        }
                                                    />
                                                </Field>
                                                <Field label="Note (optional)">
                                                    <Input
                                                        value={
                                                            statusForm.data.note
                                                        }
                                                        onChange={(event) =>
                                                            statusForm.setData(
                                                                'note',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Add a short note for the timeline"
                                                    />
                                                </Field>
                                                <Button
                                                    type="submit"
                                                    className="h-11 w-full rounded-full bg-brand text-white hover:bg-brand/90"
                                                    disabled={
                                                        statusForm.processing ||
                                                        availableStatusTransitions.length ===
                                                            0
                                                    }
                                                >
                                                    Update status
                                                </Button>
                                            </form>
                                        </Card>
                                    ) : null}

                                    {permissions.canAssignCases ? (
                                        <Card
                                            title="Assignment"
                                            icon={UserRound}
                                        >
                                            <form
                                                className="space-y-4"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    assignmentForm.transform(
                                                        (data) => ({
                                                            coordinator_id:
                                                                data.coordinator_id
                                                                    ? Number(
                                                                          data.coordinator_id,
                                                                      )
                                                                    : null,
                                                            doctor_id:
                                                                data.doctor_id
                                                                    ? Number(
                                                                          data.doctor_id,
                                                                      )
                                                                    : null,
                                                            clear_coordinator:
                                                                !data.coordinator_id,
                                                            clear_doctor:
                                                                !data.doctor_id,
                                                        }),
                                                    );
                                                    assignmentForm.patch(
                                                        `/${team.slug}/cases/${patientCase.reference}/assignment`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }}
                                            >
                                                <Field label="Coordinator">
                                                    <Select
                                                        value={
                                                            assignmentForm.data
                                                                .coordinator_id ||
                                                            UNASSIGNED
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            assignmentForm.setData(
                                                                'coordinator_id',
                                                                value ===
                                                                    UNASSIGNED
                                                                    ? ''
                                                                    : value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Unassigned" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                value={
                                                                    UNASSIGNED
                                                                }
                                                            >
                                                                Unassigned
                                                            </SelectItem>
                                                            {assignableMembers.coordinators.map(
                                                                (member) => (
                                                                    <SelectItem
                                                                        key={
                                                                            member.id
                                                                        }
                                                                        value={String(
                                                                            member.id,
                                                                        )}
                                                                    >
                                                                        {
                                                                            member.name
                                                                        }{' '}
                                                                        (
                                                                        {
                                                                            member.role_label
                                                                        }
                                                                        )
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                                <Field label="Doctor">
                                                    <Select
                                                        value={
                                                            assignmentForm.data
                                                                .doctor_id ||
                                                            UNASSIGNED
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            assignmentForm.setData(
                                                                'doctor_id',
                                                                value ===
                                                                    UNASSIGNED
                                                                    ? ''
                                                                    : value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Unassigned" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                value={
                                                                    UNASSIGNED
                                                                }
                                                            >
                                                                Unassigned
                                                            </SelectItem>
                                                            {assignableMembers.doctors.map(
                                                                (member) => (
                                                                    <SelectItem
                                                                        key={
                                                                            member.id
                                                                        }
                                                                        value={String(
                                                                            member.id,
                                                                        )}
                                                                    >
                                                                        {
                                                                            member.name
                                                                        }{' '}
                                                                        (
                                                                        {
                                                                            member.role_label
                                                                        }
                                                                        )
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                                <InputError
                                                    message={
                                                        assignmentForm.errors
                                                            .assignment
                                                    }
                                                />
                                                <Button
                                                    type="submit"
                                                    className="h-11 w-full rounded-full bg-brand text-white hover:bg-brand/90"
                                                    disabled={
                                                        assignmentForm.processing
                                                    }
                                                >
                                                    Save assignment
                                                </Button>
                                            </form>
                                        </Card>
                                    ) : null}
                                </section>
                            )}

                            <section className="grid gap-4 lg:grid-cols-2">
                                <Card title="Patient" icon={UserRound}>
                                    <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                                        <Detail
                                            icon={Mail}
                                            label="Email"
                                            value={patientCase.patient.email}
                                        />
                                        <Detail
                                            icon={Phone}
                                            label="Phone"
                                            value={patientCase.patient.phone}
                                        />
                                        <Detail
                                            icon={CalendarDays}
                                            label="Date of birth"
                                            value={
                                                patientCase.patient
                                                    .date_of_birth
                                            }
                                        />
                                        <Detail
                                            label="Gender"
                                            value={patientCase.patient.gender}
                                        />
                                        <Detail
                                            icon={Globe2}
                                            label="Country"
                                            value={
                                                patientCase.patient
                                                    .country_of_residence
                                            }
                                        />
                                        <Detail
                                            icon={Languages}
                                            label="Language"
                                            value={
                                                patientCase.patient
                                                    .preferred_language
                                            }
                                        />
                                    </dl>
                                </Card>

                                <Card title="Enquiry" icon={Stethoscope}>
                                    <div className="space-y-4">
                                        <Detail
                                            label="Reason"
                                            value={
                                                patientCase.enquiry
                                                    .reason_for_contact
                                            }
                                        />
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Detail
                                                label="Diagnosis"
                                                value={
                                                    patientCase.enquiry
                                                        .diagnosis
                                                }
                                            />
                                            <Detail
                                                label="Allergies"
                                                value={
                                                    patientCase.enquiry
                                                        .known_allergies
                                                }
                                            />
                                        </div>
                                        <Detail
                                            label="Symptoms / history"
                                            value={
                                                patientCase.enquiry
                                                    .symptoms_or_history
                                            }
                                        />
                                        <Detail
                                            label="Medications"
                                            value={
                                                patientCase.enquiry
                                                    .current_medications
                                            }
                                        />
                                        <Detail
                                            label="Department"
                                            value={
                                                patientCase.enquiry.department
                                            }
                                        />
                                    </div>
                                </Card>
                            </section>
                        </>
                    )}

                    {tab === 'documents' && (
                        <>
                            <Card
                                title="Documents"
                                description={`${patientCase.documents.length} uploaded`}
                                icon={FileText}
                            >
                                {patientCase.status ===
                                    'awaiting_coordinator_review' &&
                                permissions.canUpdateStatus ? (
                                    <div className="space-y-3 rounded-xl border border-brand/20 bg-brand/5 px-4 py-4">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900">
                                                Ready for doctor review?
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                Check the AI drafts, make sure a
                                                doctor is assigned, then send
                                                the case on.
                                            </p>
                                        </div>
                                        <ul className="space-y-1.5 text-sm">
                                            <li className="flex items-center gap-2">
                                                <span
                                                    className={
                                                        documentsReviewed
                                                            ? 'text-emerald-600'
                                                            : 'text-amber-600'
                                                    }
                                                    aria-hidden="true"
                                                >
                                                    {documentsReviewed
                                                        ? '✓'
                                                        : '○'}
                                                </span>
                                                {patientCase.documents
                                                    .length === 0
                                                    ? 'No documents uploaded'
                                                    : unreviewedDocumentCount ===
                                                        0
                                                      ? 'All AI drafts have been reviewed'
                                                      : `${unreviewedDocumentCount} AI ${unreviewedDocumentCount === 1 ? 'draft needs' : 'drafts need'} review`}
                                            </li>
                                            <li className="flex items-center gap-2">
                                                <span
                                                    className={
                                                        patientCase.assignments
                                                            .doctor
                                                            ? 'text-emerald-600'
                                                            : 'text-amber-600'
                                                    }
                                                    aria-hidden="true"
                                                >
                                                    {patientCase.assignments
                                                        .doctor
                                                        ? '✓'
                                                        : '○'}
                                                </span>
                                                {patientCase.assignments.doctor
                                                    ? `Doctor assigned: ${patientCase.assignments.doctor.name}`
                                                    : 'No doctor assigned yet'}
                                                {!patientCase.assignments
                                                    .doctor &&
                                                permissions.canAssignCases ? (
                                                    <button
                                                        type="button"
                                                        className="text-brand underline-offset-2 hover:underline"
                                                        onClick={() =>
                                                            selectTab(
                                                                'overview',
                                                            )
                                                        }
                                                    >
                                                        Assign a doctor
                                                    </button>
                                                ) : null}
                                            </li>
                                        </ul>
                                        <div className="flex flex-wrap gap-2">
                                            {canSendToDoctor ? (
                                                <Button
                                                    type="button"
                                                    className="rounded-full bg-brand text-white hover:bg-brand/90"
                                                    disabled={
                                                        !readyForDoctor ||
                                                        statusForm.processing
                                                    }
                                                    onClick={() =>
                                                        router.patch(
                                                            `/${team.slug}/cases/${patientCase.reference}/status`,
                                                            {
                                                                status: 'awaiting_doctor_review',
                                                                note: 'Coordinator review complete. Sent to doctor review.',
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Send to doctor review
                                                </Button>
                                            ) : null}
                                            {canRequestMoreInfo ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="rounded-full"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/${team.slug}/cases/${patientCase.reference}/status`,
                                                            {
                                                                status: 'additional_information_required',
                                                                note: 'Coordinator requested additional information.',
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Request more information
                                                </Button>
                                            ) : null}
                                        </div>
                                        {!readyForDoctor && canSendToDoctor ? (
                                            <p className="text-xs text-slate-500">
                                                Complete the checks above to
                                                enable sending to a doctor.
                                            </p>
                                        ) : null}
                                    </div>
                                ) : null}
                                {firstUnreviewedDocument ? (
                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                        <span>
                                            {unreviewedDocumentCount}{' '}
                                            {unreviewedDocumentCount === 1
                                                ? 'document has'
                                                : 'documents have'}{' '}
                                            an AI draft waiting for review.
                                            Drafts are not clinically approved.
                                        </span>
                                        <Button
                                            asChild
                                            size="sm"
                                            className="rounded-full"
                                        >
                                            <Link
                                                href={
                                                    firstUnreviewedDocument.review_url
                                                }
                                            >
                                                Start reviewing
                                            </Link>
                                        </Button>
                                    </div>
                                ) : null}
                                {patientCase.documents.length === 0 ? (
                                    <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center text-sm text-slate-500">
                                        No documents uploaded yet.
                                    </div>
                                ) : (
                                    <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200">
                                        {patientCase.documents.map(
                                            (document) => (
                                                <li
                                                    key={document.uuid}
                                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-slate-50/70"
                                                >
                                                    <div className="flex min-w-0 items-center gap-3">
                                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
                                                            <FileText className="size-5" />
                                                        </span>
                                                        <div className="min-w-0">
                                                            <Link
                                                                href={
                                                                    document.review_url
                                                                }
                                                                className="block truncate font-medium text-slate-900 hover:text-brand"
                                                            >
                                                                {
                                                                    document.original_filename
                                                                }
                                                            </Link>
                                                            <div className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                                                <span>
                                                                    {
                                                                        document.category
                                                                    }
                                                                </span>
                                                                <span aria-hidden="true">
                                                                    ·
                                                                </span>
                                                                <span>
                                                                    {Math.round(
                                                                        document.size_bytes /
                                                                            1024,
                                                                    )}{' '}
                                                                    KB
                                                                </span>
                                                                {document.ai_state ===
                                                                'processing' ? (
                                                                    <span className="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 font-semibold text-sky-800">
                                                                        AI
                                                                        processing
                                                                    </span>
                                                                ) : null}
                                                                {document.ai_state ===
                                                                'unavailable' ? (
                                                                    <span className="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">
                                                                        AI off ·
                                                                        review
                                                                        the
                                                                        original
                                                                    </span>
                                                                ) : null}
                                                                {document.ai_state ===
                                                                'not_analysed' ? (
                                                                    <span className="rounded-full border border-brand/30 bg-brand/5 px-2 py-0.5 font-semibold text-brand">
                                                                        Not
                                                                        analysed
                                                                        yet
                                                                    </span>
                                                                ) : null}
                                                                {document.ai_review ===
                                                                'unreviewed' ? (
                                                                    <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-semibold text-amber-800">
                                                                        AI draft
                                                                        · needs
                                                                        review
                                                                    </span>
                                                                ) : null}
                                                                {document.ai_review ===
                                                                'reviewed' ? (
                                                                    <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">
                                                                        Reviewed
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <RowActions>
                                                        <RowAction
                                                            label="Open"
                                                            icon={Eye}
                                                            href={
                                                                document.review_url
                                                            }
                                                            tone="primary"
                                                            showLabel
                                                        />
                                                        {document.download_url ? (
                                                            <RowAction
                                                                label="Download original"
                                                                icon={Download}
                                                                nativeHref={
                                                                    document.download_url
                                                                }
                                                            />
                                                        ) : null}
                                                    </RowActions>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </Card>
                        </>
                    )}

                    {tab === 'evaluation' && (
                        <>
                            {permissions.canViewClinical && clinical ? (
                                <>
                                    <Card
                                        title="Doctor evaluation"
                                        icon={Stethoscope}
                                        note="Everything here is written and approved by doctors. AI summaries from documents are never treated as a medical opinion."
                                        action={
                                            permissions.canReviewClinical ||
                                            permissions.canApproveClinical ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {permissions.canReviewClinical ? (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="rounded-full"
                                                            onClick={() =>
                                                                setPanel(
                                                                    'observation',
                                                                )
                                                            }
                                                        >
                                                            <Plus className="size-4" />
                                                            Add note
                                                        </Button>
                                                    ) : null}
                                                    {permissions.canApproveClinical ? (
                                                        <NewButton
                                                            label="Submit evaluation"
                                                            onClick={() =>
                                                                setPanel(
                                                                    'review',
                                                                )
                                                            }
                                                        />
                                                    ) : null}
                                                </div>
                                            ) : undefined
                                        }
                                    >
                                        {clinical.reviews.length === 0 ? (
                                            <EmptyRecords text="No evaluation yet. The assigned doctor submits one after reviewing the documents." />
                                        ) : (
                                            <ul className="space-y-3">
                                                {clinical.reviews.map(
                                                    (review) => (
                                                        <RecordRow
                                                            key={review.uuid}
                                                            highlight={
                                                                review.outcome ===
                                                                'need_more_information'
                                                            }
                                                        >
                                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                                <div>
                                                                    <p className="font-semibold text-slate-900">
                                                                        {review.outcome
                                                                            ? (REVIEW_OUTCOME_LABELS[
                                                                                  review
                                                                                      .outcome
                                                                              ] ??
                                                                              review.outcome_label)
                                                                            : 'Evaluation in progress'}
                                                                    </p>
                                                                    <p className="mt-0.5 text-xs text-slate-500">
                                                                        {review
                                                                            .reviewer
                                                                            ?.name ??
                                                                            'Doctor'}
                                                                        {' · '}
                                                                        {new Date(
                                                                            review.completed_at ??
                                                                                review.started_at,
                                                                        ).toLocaleString(
                                                                            [],
                                                                            {
                                                                                dateStyle:
                                                                                    'medium',
                                                                                timeStyle:
                                                                                    'short',
                                                                            },
                                                                        )}
                                                                    </p>
                                                                </div>
                                                                <span
                                                                    className={cn(
                                                                        'rounded-full px-2.5 py-1 text-xs font-medium',
                                                                        review.outcome ===
                                                                            'ready_for_proposal'
                                                                            ? 'bg-brand/10 text-brand'
                                                                            : review.outcome ===
                                                                                'need_more_information'
                                                                              ? 'bg-amber-100 text-amber-900'
                                                                              : 'bg-slate-100 text-slate-600',
                                                                    )}
                                                                >
                                                                    {
                                                                        review.status_label
                                                                    }
                                                                </span>
                                                            </div>
                                                            {review.findings ? (
                                                                <p className="whitespace-pre-wrap text-slate-700">
                                                                    {
                                                                        review.findings
                                                                    }
                                                                </p>
                                                            ) : null}
                                                            {review.additional_information_request ? (
                                                                <div className="rounded-lg bg-amber-50 px-3 py-2 text-amber-900">
                                                                    <p className="text-xs font-medium">
                                                                        Asked
                                                                        the
                                                                        patient
                                                                        for
                                                                    </p>
                                                                    <p className="whitespace-pre-wrap">
                                                                        {
                                                                            review.additional_information_request
                                                                        }
                                                                    </p>
                                                                </div>
                                                            ) : null}
                                                        </RecordRow>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                    </Card>

                                    <section className="grid gap-4 lg:grid-cols-2">
                                        <Card
                                            title="Recommendations"
                                            icon={ScrollText}
                                            description="Approved by a second doctor before a proposal can use them."
                                            action={
                                                permissions.canApproveClinical ? (
                                                    <NewButton
                                                        label="Write recommendation"
                                                        onClick={() =>
                                                            setPanel(
                                                                'recommendation',
                                                            )
                                                        }
                                                    />
                                                ) : undefined
                                            }
                                        >
                                            {clinical.recommendations.length ===
                                            0 ? (
                                                <EmptyRecords text="No recommendations yet." />
                                            ) : (
                                                <ul className="space-y-3">
                                                    {clinical.recommendations.map(
                                                        (recommendation) => (
                                                            <RecordRow
                                                                key={
                                                                    recommendation.uuid
                                                                }
                                                                highlight={
                                                                    recommendation.status ===
                                                                    'awaiting_approval'
                                                                }
                                                            >
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <span
                                                                        className={cn(
                                                                            'rounded-full px-2.5 py-1 text-xs font-medium',
                                                                            recommendation.status ===
                                                                                'approved'
                                                                                ? 'bg-brand/10 text-brand'
                                                                                : recommendation.status ===
                                                                                    'awaiting_approval'
                                                                                  ? 'bg-amber-100 text-amber-900'
                                                                                  : recommendation.status ===
                                                                                      'rejected'
                                                                                    ? 'bg-rose-100 text-rose-800'
                                                                                    : 'bg-slate-100 text-slate-600',
                                                                        )}
                                                                    >
                                                                        {RECOMMENDATION_STATUS_LABELS[
                                                                            recommendation
                                                                                .status
                                                                        ] ??
                                                                            recommendation.status_label}
                                                                    </span>
                                                                    {recommendation.version >
                                                                    1 ? (
                                                                        <span className="text-xs text-slate-500">
                                                                            Version{' '}
                                                                            {
                                                                                recommendation.version
                                                                            }
                                                                        </span>
                                                                    ) : null}
                                                                    {recommendation.is_ai_generated ? (
                                                                        <span className="rounded-full bg-[#ece9f8] px-2.5 py-1 text-xs font-medium text-[#4b3f8f]">
                                                                            Written
                                                                            by
                                                                            AI ·
                                                                            can't
                                                                            be
                                                                            approved
                                                                        </span>
                                                                    ) : null}
                                                                </div>
                                                                <p className="whitespace-pre-wrap text-slate-700">
                                                                    {
                                                                        recommendation.recommendation_text
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-slate-500">
                                                                    Written by{' '}
                                                                    {recommendation.author ??
                                                                        'a doctor'}
                                                                    {recommendation.approvals.map(
                                                                        (
                                                                            approval,
                                                                        ) =>
                                                                            ` · ${approval.outcome_label} by ${approval.approver ?? 'a doctor'} on ${new Date(approval.decided_at).toLocaleDateString()}${approval.notes ? ` (${approval.notes})` : ''}`,
                                                                    )}
                                                                </p>
                                                                {permissions.canApproveClinical &&
                                                                recommendation.status ===
                                                                    'awaiting_approval' &&
                                                                !recommendation.is_ai_generated ? (
                                                                    <form
                                                                        className="flex flex-wrap items-end gap-2 rounded-lg bg-slate-50 p-3"
                                                                        onSubmit={(
                                                                            event,
                                                                        ) => {
                                                                            event.preventDefault();
                                                                            approvalForm.post(
                                                                                `/${team.slug}/cases/${patientCase.reference}/clinical/recommendations/${recommendation.uuid}/decision`,
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            );
                                                                        }}
                                                                    >
                                                                        <div className="grid min-w-48 flex-1 gap-1.5">
                                                                            <Label className="text-xs text-slate-600">
                                                                                Note
                                                                                (optional)
                                                                            </Label>
                                                                            <Input
                                                                                className="bg-white"
                                                                                value={
                                                                                    approvalForm
                                                                                        .data
                                                                                        .notes
                                                                                }
                                                                                onChange={(
                                                                                    event,
                                                                                ) =>
                                                                                    approvalForm.setData(
                                                                                        'notes',
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                    )
                                                                                }
                                                                            />
                                                                        </div>
                                                                        <Button
                                                                            type="submit"
                                                                            size="sm"
                                                                            variant="outline"
                                                                            className="rounded-full"
                                                                            disabled={
                                                                                approvalForm.processing
                                                                            }
                                                                            onClick={() =>
                                                                                approvalForm.transform(
                                                                                    (
                                                                                        data,
                                                                                    ) => ({
                                                                                        ...data,
                                                                                        outcome:
                                                                                            'rejected',
                                                                                    }),
                                                                                )
                                                                            }
                                                                        >
                                                                            Reject
                                                                        </Button>
                                                                        <Button
                                                                            type="submit"
                                                                            size="sm"
                                                                            className="rounded-full bg-brand text-white hover:bg-brand/90"
                                                                            disabled={
                                                                                approvalForm.processing
                                                                            }
                                                                            onClick={() =>
                                                                                approvalForm.transform(
                                                                                    (
                                                                                        data,
                                                                                    ) => ({
                                                                                        ...data,
                                                                                        outcome:
                                                                                            'approved',
                                                                                    }),
                                                                                )
                                                                            }
                                                                        >
                                                                            Approve
                                                                        </Button>
                                                                    </form>
                                                                ) : null}
                                                            </RecordRow>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                        </Card>

                                        <Card
                                            title="Doctor notes"
                                            icon={NotebookPen}
                                            description="Observations recorded during the review."
                                        >
                                            {clinical.observations.length ===
                                            0 ? (
                                                <EmptyRecords text="No notes yet." />
                                            ) : (
                                                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200">
                                                    {clinical.observations.map(
                                                        (observation) => (
                                                            <li
                                                                key={
                                                                    observation.id
                                                                }
                                                                className="space-y-1 px-4 py-3 text-sm"
                                                            >
                                                                <p className="whitespace-pre-wrap text-slate-800">
                                                                    {
                                                                        observation.body
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-slate-500">
                                                                    {observation.author ??
                                                                        'Doctor'}
                                                                    {' · '}
                                                                    {observation.visibility ===
                                                                    'coordinator_shared'
                                                                        ? 'Shared with coordinators'
                                                                        : 'Doctors only'}
                                                                    {' · '}
                                                                    {new Date(
                                                                        observation.observed_at,
                                                                    ).toLocaleDateString(
                                                                        [],
                                                                        {
                                                                            dateStyle:
                                                                                'medium',
                                                                        },
                                                                    )}
                                                                </p>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                        </Card>
                                    </section>

                                    {permissions.canReviewClinical ? (
                                        <FormSheet
                                            open={panel === 'observation'}
                                            onOpenChange={(open) =>
                                                setPanel(
                                                    open ? 'observation' : null,
                                                )
                                            }
                                            title="Add a doctor note"
                                            description="A clinical observation on this case."
                                        >
                                            <form
                                                className="space-y-4"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    observationForm.post(
                                                        clinical.urls
                                                            .store_observation,
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () => {
                                                                setPanel(null);
                                                                observationForm.reset();
                                                            },
                                                        },
                                                    );
                                                }}
                                            >
                                                <Field label="Note">
                                                    <Textarea
                                                        className="min-h-32"
                                                        value={
                                                            observationForm.data
                                                                .body
                                                        }
                                                        onChange={(event) =>
                                                            observationForm.setData(
                                                                'body',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field label="Who can see it">
                                                    <Select
                                                        value={
                                                            observationForm.data
                                                                .visibility
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            observationForm.setData(
                                                                'visibility',
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Who can see it" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="internal">
                                                                Doctors only
                                                            </SelectItem>
                                                            <SelectItem value="coordinator_shared">
                                                                Doctors and
                                                                coordinators
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                                <InputError
                                                    message={
                                                        observationForm.errors
                                                            .body
                                                    }
                                                />
                                                <Button
                                                    type="submit"
                                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                                    disabled={
                                                        observationForm.processing
                                                    }
                                                >
                                                    Save note
                                                </Button>
                                            </form>
                                        </FormSheet>
                                    ) : null}

                                    {permissions.canApproveClinical ? (
                                        <>
                                            <FormSheet
                                                open={
                                                    panel === 'recommendation'
                                                }
                                                onOpenChange={(open) =>
                                                    setPanel(
                                                        open
                                                            ? 'recommendation'
                                                            : null,
                                                    )
                                                }
                                                title="Write a recommendation"
                                                description="Another doctor approves it before a treatment proposal can be based on it."
                                            >
                                                <form
                                                    className="space-y-4"
                                                    onSubmit={(event) => {
                                                        event.preventDefault();
                                                        recommendationForm.transform(
                                                            (data) => ({
                                                                ...data,
                                                                submit_for_approval: false,
                                                            }),
                                                        );
                                                        recommendationForm.post(
                                                            clinical.urls
                                                                .store_recommendation,
                                                            {
                                                                preserveScroll: true,
                                                                onSuccess:
                                                                    () => {
                                                                        setPanel(
                                                                            null,
                                                                        );
                                                                        recommendationForm.reset();
                                                                    },
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <Field label="Recommendation">
                                                        <Textarea
                                                            className="min-h-40"
                                                            value={
                                                                recommendationForm
                                                                    .data
                                                                    .recommendation_text
                                                            }
                                                            onChange={(event) =>
                                                                recommendationForm.setData(
                                                                    'recommendation_text',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                    <InputError
                                                        message={
                                                            recommendationForm
                                                                .errors
                                                                .recommendation_text
                                                        }
                                                    />
                                                    <div className="flex flex-wrap gap-2">
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            className="h-11 rounded-full"
                                                            disabled={
                                                                recommendationForm.processing
                                                            }
                                                        >
                                                            Save draft
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                                            disabled={
                                                                recommendationForm.processing
                                                            }
                                                            onClick={() => {
                                                                recommendationForm.transform(
                                                                    (data) => ({
                                                                        ...data,
                                                                        submit_for_approval: true,
                                                                    }),
                                                                );
                                                                recommendationForm.post(
                                                                    clinical
                                                                        .urls
                                                                        .store_recommendation,
                                                                    {
                                                                        preserveScroll: true,
                                                                        onSuccess:
                                                                            () => {
                                                                                setPanel(
                                                                                    null,
                                                                                );
                                                                                recommendationForm.reset();
                                                                            },
                                                                    },
                                                                );
                                                            }}
                                                        >
                                                            Send for approval
                                                        </Button>
                                                    </div>
                                                </form>
                                            </FormSheet>

                                            <FormSheet
                                                open={panel === 'review'}
                                                onOpenChange={(open) =>
                                                    setPanel(
                                                        open ? 'review' : null,
                                                    )
                                                }
                                                title="Submit your evaluation"
                                                description="Your findings move the case on: either to a treatment proposal, or back to the patient for more information."
                                            >
                                                <form
                                                    className="space-y-4"
                                                    onSubmit={(event) => {
                                                        event.preventDefault();
                                                        reviewForm.post(
                                                            clinical.urls
                                                                .store_review,
                                                            {
                                                                preserveScroll: true,
                                                                onSuccess:
                                                                    () => {
                                                                        setPanel(
                                                                            null,
                                                                        );
                                                                        reviewForm.reset();
                                                                    },
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <Field label="Result">
                                                        <div className="grid gap-2 sm:grid-cols-2">
                                                            {[
                                                                {
                                                                    value: 'ready_for_proposal',
                                                                    label: 'Ready for a proposal',
                                                                    hint: 'The coordinator prepares the treatment proposal.',
                                                                },
                                                                {
                                                                    value: 'need_more_information',
                                                                    label: 'Need more information',
                                                                    hint: 'A message asking the patient is prepared for approval.',
                                                                },
                                                            ].map((option) => (
                                                                <button
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    type="button"
                                                                    onClick={() =>
                                                                        reviewForm.setData(
                                                                            'outcome',
                                                                            option.value,
                                                                        )
                                                                    }
                                                                    className={cn(
                                                                        'rounded-xl border px-3 py-2.5 text-left text-sm transition',
                                                                        reviewForm
                                                                            .data
                                                                            .outcome ===
                                                                            option.value
                                                                            ? 'border-brand bg-brand/5 ring-1 ring-brand'
                                                                            : 'border-slate-200 hover:border-brand/40',
                                                                    )}
                                                                >
                                                                    <span className="block font-medium text-slate-900">
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </span>
                                                                    <span className="block text-xs text-slate-500">
                                                                        {
                                                                            option.hint
                                                                        }
                                                                    </span>
                                                                </button>
                                                            ))}
                                                        </div>
                                                    </Field>
                                                    <Field label="Findings">
                                                        <Textarea
                                                            className="min-h-32"
                                                            value={
                                                                reviewForm.data
                                                                    .findings
                                                            }
                                                            onChange={(event) =>
                                                                reviewForm.setData(
                                                                    'findings',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </Field>
                                                    {reviewForm.data.outcome ===
                                                    'need_more_information' ? (
                                                        <Field label="What should the patient send?">
                                                            <Textarea
                                                                className="min-h-24"
                                                                placeholder="Recent bloodwork and an ECG from the last 3 months"
                                                                value={
                                                                    reviewForm
                                                                        .data
                                                                        .additional_information_request
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    reviewForm.setData(
                                                                        'additional_information_request',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </Field>
                                                    ) : null}
                                                    <InputError
                                                        message={
                                                            reviewForm.errors
                                                                .findings
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            reviewForm.errors
                                                                .additional_information_request
                                                        }
                                                    />
                                                    <Button
                                                        type="submit"
                                                        className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                                        disabled={
                                                            reviewForm.processing
                                                        }
                                                    >
                                                        Submit evaluation
                                                    </Button>
                                                </form>
                                            </FormSheet>
                                        </>
                                    ) : null}
                                </>
                            ) : null}
                        </>
                    )}

                    {tab === 'proposal' && (
                        <>
                            {proposals ? (
                                <Card
                                    title="Treatment proposal"
                                    icon={ScrollText}
                                    note="A doctor approves every proposal before it goes to the patient. Medical recommendations are approved separately in Evaluation."
                                    action={
                                        permissions.canManageProposals ? (
                                            <NewButton
                                                label="New proposal"
                                                onClick={() =>
                                                    setPanel('proposal')
                                                }
                                            />
                                        ) : undefined
                                    }
                                >
                                    {proposals.items.length === 0 ? (
                                        <EmptyRecords text="No treatment proposal yet. Create one when the doctor's evaluation is ready." />
                                    ) : (
                                        <ul className="space-y-3">
                                            {proposals.items.map((proposal) => {
                                                const stopped = [
                                                    'patient_declined',
                                                    'cancelled',
                                                    'expired',
                                                ].includes(proposal.status);
                                                const trackerKey =
                                                    proposal.status ===
                                                    'patient_declined'
                                                        ? 'patient_accepted'
                                                        : PROPOSAL_STEPS.some(
                                                                (step) =>
                                                                    step.key ===
                                                                    proposal.status,
                                                            )
                                                          ? proposal.status
                                                          : 'draft';

                                                return (
                                                    <RecordRow
                                                        key={proposal.uuid}
                                                        highlight={
                                                            proposal.is_current
                                                        }
                                                    >
                                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                                            <div className="min-w-0">
                                                                <p className="font-semibold text-slate-900">
                                                                    {
                                                                        proposal.proposed_procedure
                                                                    }
                                                                </p>
                                                                <p className="mt-0.5 text-xs text-slate-500">
                                                                    <span className="font-mono text-slate-600">
                                                                        {
                                                                            proposal.reference
                                                                        }
                                                                    </span>
                                                                    {proposal.version >
                                                                    1
                                                                        ? ` · version ${proposal.version}`
                                                                        : ''}
                                                                    {proposal.is_current
                                                                        ? ''
                                                                        : ' · replaced by a newer version'}
                                                                </p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-semibold text-slate-900">
                                                                    {proposal.estimated_cost
                                                                        ? `${proposal.estimated_cost} ${proposal.currency}`
                                                                        : 'Cost to be confirmed'}
                                                                </p>
                                                                <p className="text-xs text-slate-500">
                                                                    {[
                                                                        proposal.expected_duration,
                                                                        proposal
                                                                            .responsible_physician
                                                                            ?.name,
                                                                    ]
                                                                        .filter(
                                                                            Boolean,
                                                                        )
                                                                        .join(
                                                                            ' · ',
                                                                        )}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        {[
                                                            'revised',
                                                            'cancelled',
                                                            'expired',
                                                        ].includes(
                                                            proposal.status,
                                                        ) ? (
                                                            <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                                                {
                                                                    proposal.status_label
                                                                }
                                                            </span>
                                                        ) : (
                                                            <StepTracker
                                                                steps={PROPOSAL_STEPS.map(
                                                                    (step) =>
                                                                        step.key ===
                                                                            'patient_accepted' &&
                                                                        proposal.status ===
                                                                            'patient_declined'
                                                                            ? {
                                                                                  ...step,
                                                                                  label: 'Declined',
                                                                              }
                                                                            : step,
                                                                )}
                                                                current={
                                                                    trackerKey
                                                                }
                                                                stopped={
                                                                    stopped
                                                                }
                                                            />
                                                        )}

                                                        {proposal.line_items
                                                            .length > 0 ? (
                                                            <ul className="divide-y divide-slate-100 rounded-lg border border-slate-100 text-xs text-slate-600">
                                                                {proposal.line_items.map(
                                                                    (item) => (
                                                                        <li
                                                                            key={
                                                                                item.id
                                                                            }
                                                                            className="flex justify-between gap-3 px-3 py-1.5"
                                                                        >
                                                                            <span>
                                                                                {item.quantity >
                                                                                1
                                                                                    ? `${item.quantity} × `
                                                                                    : ''}
                                                                                {
                                                                                    item.description
                                                                                }
                                                                            </span>
                                                                            {item.unit_cost ? (
                                                                                <span>
                                                                                    {
                                                                                        item.unit_cost
                                                                                    }{' '}
                                                                                    {
                                                                                        item.currency
                                                                                    }
                                                                                </span>
                                                                            ) : null}
                                                                        </li>
                                                                    ),
                                                                )}
                                                            </ul>
                                                        ) : null}

                                                        {proposal.is_current ? (
                                                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2.5">
                                                                <p className="text-xs text-slate-600">
                                                                    {proposal.status ===
                                                                    'draft'
                                                                        ? 'Next: a doctor approves the proposal before it can go to the patient.'
                                                                        : proposal.status ===
                                                                            'awaiting_clinical_approval'
                                                                          ? permissions.canApproveProposals
                                                                              ? 'Waiting for your clinical approval.'
                                                                              : 'Waiting for a doctor to approve it.'
                                                                          : proposal.status ===
                                                                              'approved'
                                                                            ? 'Approved. Sending emails the patient a link to accept or decline.'
                                                                            : proposal.status ===
                                                                                'sent_to_patient'
                                                                              ? `Sent${proposal.sent_at ? ` on ${new Date(proposal.sent_at).toLocaleDateString()}` : ''}. The patient can accept or decline on their case page.`
                                                                              : proposal.status ===
                                                                                  'patient_accepted'
                                                                                ? 'The patient accepted. Next: plan the visit.'
                                                                                : proposal.status ===
                                                                                    'patient_declined'
                                                                                  ? 'The patient declined. You can revise and send a new version.'
                                                                                  : proposal.status_label}
                                                                </p>
                                                                <div className="flex flex-wrap gap-2">
                                                                    {permissions.canManageProposals &&
                                                                    proposal.status ===
                                                                        'draft' ? (
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            className="rounded-full bg-brand text-white hover:bg-brand/90"
                                                                            disabled={
                                                                                proposalTransitionForm.processing
                                                                            }
                                                                            onClick={() =>
                                                                                runProposalAction(
                                                                                    proposal.transition_url,
                                                                                    'submit_for_approval',
                                                                                )
                                                                            }
                                                                        >
                                                                            Send
                                                                            for
                                                                            clinical
                                                                            approval
                                                                        </Button>
                                                                    ) : null}
                                                                    {permissions.canApproveProposals &&
                                                                    proposal.status ===
                                                                        'awaiting_clinical_approval' ? (
                                                                        <>
                                                                            <Button
                                                                                type="button"
                                                                                size="sm"
                                                                                variant="outline"
                                                                                className="rounded-full"
                                                                                disabled={
                                                                                    proposalTransitionForm.processing
                                                                                }
                                                                                onClick={() =>
                                                                                    runProposalAction(
                                                                                        proposal.transition_url,
                                                                                        'return_to_draft',
                                                                                        {
                                                                                            notes: 'Returned for revision.',
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                Send
                                                                                back
                                                                                for
                                                                                changes
                                                                            </Button>
                                                                            <Button
                                                                                type="button"
                                                                                size="sm"
                                                                                className="rounded-full bg-brand text-white hover:bg-brand/90"
                                                                                disabled={
                                                                                    proposalTransitionForm.processing
                                                                                }
                                                                                onClick={() =>
                                                                                    runProposalAction(
                                                                                        proposal.transition_url,
                                                                                        'approve',
                                                                                        {
                                                                                            notes: 'Clinically approved for administrative packaging.',
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                Approve
                                                                            </Button>
                                                                        </>
                                                                    ) : null}
                                                                    {permissions.canManageProposals &&
                                                                    proposal.status ===
                                                                        'approved' ? (
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            className="rounded-full bg-brand text-white hover:bg-brand/90"
                                                                            disabled={
                                                                                proposalTransitionForm.processing
                                                                            }
                                                                            onClick={() =>
                                                                                runProposalAction(
                                                                                    proposal.transition_url,
                                                                                    'send',
                                                                                )
                                                                            }
                                                                        >
                                                                            Send
                                                                            to
                                                                            patient
                                                                        </Button>
                                                                    ) : null}
                                                                    {permissions.canManageProposals &&
                                                                    proposal.status ===
                                                                        'sent_to_patient' ? (
                                                                        <>
                                                                            <span className="self-center text-xs text-slate-500">
                                                                                Answered
                                                                                by
                                                                                phone?
                                                                            </span>
                                                                            <Button
                                                                                type="button"
                                                                                size="sm"
                                                                                variant="outline"
                                                                                className="rounded-full"
                                                                                disabled={
                                                                                    proposalTransitionForm.processing
                                                                                }
                                                                                onClick={() =>
                                                                                    runProposalAction(
                                                                                        proposal.transition_url,
                                                                                        'record_patient_response',
                                                                                        {
                                                                                            patient_response:
                                                                                                'declined',
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                Record
                                                                                declined
                                                                            </Button>
                                                                            <Button
                                                                                type="button"
                                                                                size="sm"
                                                                                variant="outline"
                                                                                className="rounded-full"
                                                                                disabled={
                                                                                    proposalTransitionForm.processing
                                                                                }
                                                                                onClick={() =>
                                                                                    runProposalAction(
                                                                                        proposal.transition_url,
                                                                                        'record_patient_response',
                                                                                        {
                                                                                            patient_response:
                                                                                                'accepted',
                                                                                        },
                                                                                    )
                                                                                }
                                                                            >
                                                                                Record
                                                                                accepted
                                                                            </Button>
                                                                        </>
                                                                    ) : null}
                                                                    {proposal.status ===
                                                                        'patient_accepted' &&
                                                                    appointments ? (
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            className="rounded-full bg-brand text-white hover:bg-brand/90"
                                                                            onClick={() =>
                                                                                selectTab(
                                                                                    'visit',
                                                                                )
                                                                            }
                                                                        >
                                                                            Plan
                                                                            the
                                                                            visit
                                                                        </Button>
                                                                    ) : null}
                                                                </div>
                                                            </div>
                                                        ) : null}
                                                        <InputError
                                                            message={
                                                                proposalTransitionForm
                                                                    .errors
                                                                    .proposal
                                                            }
                                                        />
                                                    </RecordRow>
                                                );
                                            })}
                                        </ul>
                                    )}

                                    {permissions.canManageProposals ? (
                                        <FormSheet
                                            open={panel === 'proposal'}
                                            onOpenChange={(open) =>
                                                setPanel(
                                                    open ? 'proposal' : null,
                                                )
                                            }
                                            title="New treatment proposal"
                                            description="Saved as a draft. A doctor approves it before it can be sent to the patient."
                                        >
                                            <form
                                                className="space-y-4"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    proposalForm.transform(
                                                        (data) => ({
                                                            department_id:
                                                                data.department_id
                                                                    ? Number(
                                                                          data.department_id,
                                                                      )
                                                                    : null,
                                                            responsible_physician_id:
                                                                data.responsible_physician_id
                                                                    ? Number(
                                                                          data.responsible_physician_id,
                                                                      )
                                                                    : null,
                                                            clinical_recommendation_id:
                                                                data.clinical_recommendation_id &&
                                                                data.clinical_recommendation_id !==
                                                                    UNASSIGNED
                                                                    ? Number(
                                                                          data.clinical_recommendation_id,
                                                                      )
                                                                    : null,
                                                            proposed_procedure:
                                                                data.proposed_procedure,
                                                            estimated_cost:
                                                                data.estimated_cost
                                                                    ? Number(
                                                                          data.estimated_cost,
                                                                      )
                                                                    : null,
                                                            currency:
                                                                data.currency,
                                                            expected_duration:
                                                                data.expected_duration ||
                                                                null,
                                                            required_appointments:
                                                                data.required_appointments
                                                                    ? Number(
                                                                          data.required_appointments,
                                                                      )
                                                                    : null,
                                                            additional_information:
                                                                data.additional_information ||
                                                                null,
                                                            valid_until:
                                                                data.valid_until ||
                                                                null,
                                                            items: data.items
                                                                .filter(
                                                                    (item) =>
                                                                        item.description.trim() !==
                                                                        '',
                                                                )
                                                                .map(
                                                                    (item) => ({
                                                                        description:
                                                                            item.description,
                                                                        quantity:
                                                                            Number(
                                                                                item.quantity ||
                                                                                    1,
                                                                            ),
                                                                        unit_cost:
                                                                            item.unit_cost
                                                                                ? Number(
                                                                                      item.unit_cost,
                                                                                  )
                                                                                : null,
                                                                        currency:
                                                                            data.currency,
                                                                    }),
                                                                ),
                                                        }),
                                                    );
                                                    proposalForm.post(
                                                        proposals.urls.store,
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () => {
                                                                setPanel(null);
                                                                proposalForm.reset();
                                                            },
                                                        },
                                                    );
                                                }}
                                            >
                                                <div className="grid gap-3 lg:grid-cols-2">
                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>
                                                            Proposed procedure
                                                        </Label>
                                                        <Input
                                                            value={
                                                                proposalForm
                                                                    .data
                                                                    .proposed_procedure
                                                            }
                                                            onChange={(event) =>
                                                                proposalForm.setData(
                                                                    'proposed_procedure',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>
                                                            Estimated cost
                                                        </Label>
                                                        <Input
                                                            value={
                                                                proposalForm
                                                                    .data
                                                                    .estimated_cost
                                                            }
                                                            onChange={(event) =>
                                                                proposalForm.setData(
                                                                    'estimated_cost',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>Currency</Label>
                                                        <Input
                                                            value={
                                                                proposalForm
                                                                    .data
                                                                    .currency
                                                            }
                                                            onChange={(event) =>
                                                                proposalForm.setData(
                                                                    'currency',
                                                                    event.target.value.toUpperCase(),
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>
                                                            Expected duration
                                                        </Label>
                                                        <Input
                                                            value={
                                                                proposalForm
                                                                    .data
                                                                    .expected_duration
                                                            }
                                                            onChange={(event) =>
                                                                proposalForm.setData(
                                                                    'expected_duration',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>
                                                            Required
                                                            appointments
                                                        </Label>
                                                        <Input
                                                            value={
                                                                proposalForm
                                                                    .data
                                                                    .required_appointments
                                                            }
                                                            onChange={(event) =>
                                                                proposalForm.setData(
                                                                    'required_appointments',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>
                                                            Based on the
                                                            doctor's
                                                            recommendation
                                                        </Label>
                                                        <Select
                                                            value={
                                                                proposalForm
                                                                    .data
                                                                    .clinical_recommendation_id ||
                                                                UNASSIGNED
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                proposalForm.setData(
                                                                    'clinical_recommendation_id',
                                                                    value ===
                                                                        UNASSIGNED
                                                                        ? ''
                                                                        : value,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder="Not linked" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem
                                                                    value={
                                                                        UNASSIGNED
                                                                    }
                                                                >
                                                                    Not linked
                                                                </SelectItem>
                                                                {proposals.approved_recommendations.map(
                                                                    (
                                                                        recommendation,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                recommendation.id
                                                                            }
                                                                            value={String(
                                                                                recommendation.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                recommendation.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>Line item</Label>
                                                        <Input
                                                            placeholder="Service description"
                                                            value={
                                                                proposalForm
                                                                    .data
                                                                    .items[0]
                                                                    ?.description ??
                                                                ''
                                                            }
                                                            onChange={(event) =>
                                                                proposalForm.setData(
                                                                    'items',
                                                                    [
                                                                        {
                                                                            ...proposalForm
                                                                                .data
                                                                                .items[0],
                                                                            description:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    ],
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                                <InputError
                                                    message={
                                                        proposalForm.errors
                                                            .proposed_procedure
                                                    }
                                                />
                                                <Button
                                                    type="submit"
                                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                                    disabled={
                                                        proposalForm.processing
                                                    }
                                                >
                                                    Save draft
                                                </Button>
                                            </form>
                                        </FormSheet>
                                    ) : null}
                                </Card>
                            ) : null}
                        </>
                    )}

                    {tab === 'visit' && (
                        <>
                            {appointments ? (
                                <Card
                                    title="Appointments"
                                    icon={CalendarCheck}
                                    note="An appointment is only confirmed once the hospital has checked the slot. CareFlow does not book it in the hospital system."
                                    action={
                                        permissions.canManageAppointments ? (
                                            <NewButton
                                                label="Propose appointment"
                                                onClick={() =>
                                                    setPanel('appointment')
                                                }
                                            />
                                        ) : undefined
                                    }
                                >
                                    {appointments.items.length === 0 ? (
                                        <EmptyRecords text="No appointment yet. Propose one once the patient accepts the proposal." />
                                    ) : (
                                        <ul className="space-y-3">
                                            {appointments.items.map(
                                                (appointment) => {
                                                    const when =
                                                        appointment.confirmed_starts_at ??
                                                        appointment.proposed_starts_at;
                                                    const closed = [
                                                        'cancelled',
                                                        'completed',
                                                    ].includes(
                                                        appointment.status,
                                                    );
                                                    const trackerKey =
                                                        appointment.status ===
                                                        'rescheduled'
                                                            ? 'awaiting_confirmation'
                                                            : appointment.status ===
                                                                'cancelled'
                                                              ? 'proposed'
                                                              : appointment.status;

                                                    return (
                                                        <RecordRow
                                                            key={
                                                                appointment.uuid
                                                            }
                                                            highlight={!closed}
                                                        >
                                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                                <div className="min-w-0">
                                                                    <p className="font-semibold text-slate-900">
                                                                        {
                                                                            appointment.appointment_type_label
                                                                        }
                                                                        {appointment.department
                                                                            ? ` · ${appointment.department}`
                                                                            : ''}
                                                                    </p>
                                                                    <p className="mt-0.5 text-xs text-slate-500">
                                                                        {[
                                                                            appointment.location,
                                                                            appointment
                                                                                .doctor
                                                                                ?.name,
                                                                        ]
                                                                            .filter(
                                                                                Boolean,
                                                                            )
                                                                            .join(
                                                                                ' · ',
                                                                            ) ||
                                                                            'Location to be confirmed'}
                                                                    </p>
                                                                </div>
                                                                <div className="text-right">
                                                                    <p className="font-semibold text-slate-900">
                                                                        {when
                                                                            ? new Date(
                                                                                  when,
                                                                              ).toLocaleString(
                                                                                  [],
                                                                                  {
                                                                                      dateStyle:
                                                                                          'medium',
                                                                                      timeStyle:
                                                                                          'short',
                                                                                  },
                                                                              )
                                                                            : 'Time to be confirmed'}
                                                                    </p>
                                                                    <p className="text-xs text-slate-500">
                                                                        {appointment.confirmed_starts_at
                                                                            ? 'Confirmed time'
                                                                            : 'Proposed time'}
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            {appointment.status ===
                                                            'cancelled' ? (
                                                                <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                                                    Cancelled
                                                                </span>
                                                            ) : (
                                                                <StepTracker
                                                                    steps={
                                                                        APPOINTMENT_STEPS
                                                                    }
                                                                    current={
                                                                        trackerKey
                                                                    }
                                                                />
                                                            )}

                                                            {!closed ? (
                                                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2.5">
                                                                    <p className="text-xs text-slate-600">
                                                                        {appointment.status ===
                                                                        'proposed'
                                                                            ? 'Next: check the slot with the hospital scheduling team.'
                                                                            : appointment.status ===
                                                                                'confirmed'
                                                                              ? `Confirmed${appointment.confirmed_by ? ` by ${appointment.confirmed_by}` : ''}. The patient was emailed the details.`
                                                                              : 'Once the hospital confirms the slot, confirm it here. The patient is emailed the date, time and place.'}
                                                                    </p>
                                                                    {permissions.canManageAppointments ? (
                                                                        <div className="flex flex-wrap gap-2">
                                                                            <Button
                                                                                type="button"
                                                                                size="sm"
                                                                                variant="outline"
                                                                                className="rounded-full"
                                                                                disabled={
                                                                                    appointmentTransitionForm.processing
                                                                                }
                                                                                onClick={() => {
                                                                                    if (
                                                                                        window.confirm(
                                                                                            'Cancel this appointment?',
                                                                                        )
                                                                                    ) {
                                                                                        runAppointmentAction(
                                                                                            appointment,
                                                                                            'cancel',
                                                                                            {
                                                                                                notes: 'Cancelled by coordinator.',
                                                                                            },
                                                                                        );
                                                                                    }
                                                                                }}
                                                                            >
                                                                                Cancel
                                                                            </Button>
                                                                            {appointment.status ===
                                                                            'proposed' ? (
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    className="rounded-full bg-brand text-white hover:bg-brand/90"
                                                                                    disabled={
                                                                                        appointmentTransitionForm.processing
                                                                                    }
                                                                                    onClick={() =>
                                                                                        runAppointmentAction(
                                                                                            appointment,
                                                                                            'request_confirmation',
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Ask
                                                                                    hospital
                                                                                    to
                                                                                    confirm
                                                                                </Button>
                                                                            ) : null}
                                                                            {[
                                                                                'awaiting_confirmation',
                                                                                'rescheduled',
                                                                            ].includes(
                                                                                appointment.status,
                                                                            ) ? (
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    className="rounded-full bg-brand text-white hover:bg-brand/90"
                                                                                    disabled={
                                                                                        appointmentTransitionForm.processing
                                                                                    }
                                                                                    onClick={() =>
                                                                                        runAppointmentAction(
                                                                                            appointment,
                                                                                            'confirm',
                                                                                            {
                                                                                                hospital_slot_verified: true,
                                                                                                confirmed_starts_at:
                                                                                                    appointment.proposed_starts_at ??
                                                                                                    '',
                                                                                                confirmed_ends_at:
                                                                                                    appointment.proposed_ends_at ??
                                                                                                    '',
                                                                                                notes: 'Confirmed with hospital scheduling.',
                                                                                            },
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Hospital
                                                                                    confirmed
                                                                                    it
                                                                                </Button>
                                                                            ) : null}
                                                                        </div>
                                                                    ) : null}
                                                                </div>
                                                            ) : null}
                                                            <InputError
                                                                message={
                                                                    (
                                                                        appointmentTransitionForm.errors as Record<
                                                                            string,
                                                                            | string
                                                                            | undefined
                                                                        >
                                                                    )
                                                                        .appointment
                                                                }
                                                            />
                                                        </RecordRow>
                                                    );
                                                },
                                            )}
                                        </ul>
                                    )}

                                    {permissions.canManageAppointments ? (
                                        <FormSheet
                                            open={panel === 'appointment'}
                                            onOpenChange={(open) =>
                                                setPanel(
                                                    open ? 'appointment' : null,
                                                )
                                            }
                                            title="Propose an appointment"
                                            description="Pick a time to check with the hospital. The patient is told once the hospital confirms it."
                                        >
                                            <form
                                                className="space-y-4"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    appointmentForm.transform(
                                                        (data) => ({
                                                            department_id:
                                                                data.department_id
                                                                    ? Number(
                                                                          data.department_id,
                                                                      )
                                                                    : null,
                                                            doctor_id:
                                                                data.doctor_id &&
                                                                data.doctor_id !==
                                                                    UNASSIGNED
                                                                    ? Number(
                                                                          data.doctor_id,
                                                                      )
                                                                    : null,
                                                            appointment_type:
                                                                data.appointment_type,
                                                            proposed_starts_at:
                                                                data.proposed_starts_at,
                                                            proposed_ends_at:
                                                                data.proposed_ends_at ||
                                                                null,
                                                            location:
                                                                data.location ||
                                                                null,
                                                            meeting_instructions:
                                                                data.meeting_instructions ||
                                                                null,
                                                            notes:
                                                                data.notes ||
                                                                null,
                                                            external_his_reference:
                                                                data.external_his_reference ||
                                                                null,
                                                        }),
                                                    );
                                                    appointmentForm.post(
                                                        appointments.urls.store,
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () => {
                                                                setPanel(null);
                                                                appointmentForm.reset();
                                                            },
                                                        },
                                                    );
                                                }}
                                            >
                                                <div className="grid gap-3 lg:grid-cols-2">
                                                    <div className="grid gap-2">
                                                        <Label>Type</Label>
                                                        <Select
                                                            value={
                                                                appointmentForm
                                                                    .data
                                                                    .appointment_type ||
                                                                undefined
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                appointmentForm.setData(
                                                                    'appointment_type',
                                                                    value,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder="Select type" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {appointments.type_options.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option.value
                                                                            }
                                                                            value={
                                                                                option.value
                                                                            }
                                                                        >
                                                                            {
                                                                                option.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>
                                                            Proposed start
                                                        </Label>
                                                        <Input
                                                            type="datetime-local"
                                                            value={
                                                                appointmentForm
                                                                    .data
                                                                    .proposed_starts_at
                                                            }
                                                            onChange={(event) =>
                                                                appointmentForm.setData(
                                                                    'proposed_starts_at',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>Location</Label>
                                                        <Input
                                                            value={
                                                                appointmentForm
                                                                    .data
                                                                    .location
                                                            }
                                                            onChange={(event) =>
                                                                appointmentForm.setData(
                                                                    'location',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>Doctor</Label>
                                                        <Select
                                                            value={
                                                                appointmentForm
                                                                    .data
                                                                    .doctor_id ||
                                                                UNASSIGNED
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                appointmentForm.setData(
                                                                    'doctor_id',
                                                                    value ===
                                                                        UNASSIGNED
                                                                        ? ''
                                                                        : value,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder="Unassigned" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem
                                                                    value={
                                                                        UNASSIGNED
                                                                    }
                                                                >
                                                                    Unassigned
                                                                </SelectItem>
                                                                {assignableMembers.doctors.map(
                                                                    (
                                                                        doctor,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                doctor.id
                                                                            }
                                                                            value={String(
                                                                                doctor.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                doctor.name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </div>
                                                <InputError
                                                    message={
                                                        appointmentForm.errors
                                                            .proposed_starts_at ||
                                                        appointmentForm.errors
                                                            .appointment
                                                    }
                                                />
                                                <Button
                                                    type="submit"
                                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                                    disabled={
                                                        appointmentForm.processing
                                                    }
                                                >
                                                    Save proposed appointment
                                                </Button>
                                            </form>
                                        </FormSheet>
                                    ) : null}
                                </Card>
                            ) : null}
                        </>
                    )}

                    {tab === 'visit' && (
                        <>
                            {travel ? (
                                <Card
                                    title="Travel and stay"
                                    icon={Plane}
                                    note="For arrival, pickup and accommodation only. Never store passport numbers here."
                                    action={
                                        permissions.canManageTravel ? (
                                            <NewButton
                                                label={
                                                    travel.arrangement
                                                        ? 'Edit travel'
                                                        : 'Add travel details'
                                                }
                                                icon={
                                                    travel.arrangement
                                                        ? Pencil
                                                        : Plus
                                                }
                                                onClick={() =>
                                                    setPanel('travel')
                                                }
                                            />
                                        ) : undefined
                                    }
                                >
                                    {travel.arrangement ? (
                                        <div className="space-y-4">
                                            {travel.arrangement.status ===
                                            'cancelled' ? (
                                                <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                                    Cancelled
                                                </span>
                                            ) : (
                                                <StepTracker
                                                    steps={TRAVEL_STEPS}
                                                    current={
                                                        travel.arrangement
                                                            .status
                                                    }
                                                />
                                            )}
                                            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                                <Detail
                                                    label="Arrival"
                                                    value={
                                                        travel.arrangement
                                                            .expected_arrival_date
                                                            ? new Date(
                                                                  travel
                                                                      .arrangement
                                                                      .expected_arrival_date,
                                                              ).toLocaleDateString(
                                                                  [],
                                                                  {
                                                                      dateStyle:
                                                                          'medium',
                                                                  },
                                                              )
                                                            : null
                                                    }
                                                    icon={CalendarDays}
                                                />
                                                <Detail
                                                    label="Departure"
                                                    value={
                                                        travel.arrangement
                                                            .expected_departure_date
                                                            ? new Date(
                                                                  travel
                                                                      .arrangement
                                                                      .expected_departure_date,
                                                              ).toLocaleDateString(
                                                                  [],
                                                                  {
                                                                      dateStyle:
                                                                          'medium',
                                                                  },
                                                              )
                                                            : null
                                                    }
                                                    icon={CalendarDays}
                                                />
                                                <Detail
                                                    label="Airport pickup"
                                                    value={
                                                        travel.arrangement
                                                            .airport_pickup_required
                                                            ? travel.arrangement
                                                                  .airport_pickup_details ||
                                                              'Needed'
                                                            : 'Not needed'
                                                    }
                                                    icon={Plane}
                                                />
                                                <Detail
                                                    label="Visa help"
                                                    value={
                                                        travel.arrangement
                                                            .visa_assistance_status_label
                                                    }
                                                    icon={Globe2}
                                                />
                                                <div className="sm:col-span-2">
                                                    <Detail
                                                        label="Accommodation"
                                                        value={[
                                                            travel.arrangement
                                                                .accommodation,
                                                            travel.arrangement
                                                                .hotel_details,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    />
                                                </div>
                                                <div className="sm:col-span-2">
                                                    <Detail
                                                        label="Flight"
                                                        value={
                                                            travel.arrangement
                                                                .flight_information
                                                        }
                                                    />
                                                </div>
                                            </dl>
                                            {travel.arrangement.checklist
                                                .length > 0 ? (
                                                <ul className="flex flex-wrap gap-2">
                                                    {travel.arrangement.checklist.map(
                                                        (item) => (
                                                            <li
                                                                key={item.key}
                                                                className={cn(
                                                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                                                                    item.done
                                                                        ? 'bg-brand/10 text-brand'
                                                                        : 'bg-slate-100 text-slate-500',
                                                                )}
                                                            >
                                                                {item.done ? (
                                                                    <Check className="size-3" />
                                                                ) : (
                                                                    <Circle className="size-3" />
                                                                )}
                                                                {item.label}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            ) : null}
                                            <p className="rounded-lg bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                                                {travel.arrangement.status ===
                                                    'confirmed' ||
                                                travel.arrangement.status ===
                                                    'completed'
                                                    ? 'Confirmed. A message with these details was prepared for the patient in Messages.'
                                                    : 'When everything is arranged, set the status to Confirmed. A message with the details is prepared for you to approve.'}
                                            </p>
                                        </div>
                                    ) : (
                                        <EmptyRecords text="No travel details yet. Add them once the visit dates are clear." />
                                    )}
                                    {permissions.canManageTravel ? (
                                        <FormSheet
                                            open={panel === 'travel'}
                                            onOpenChange={(open) =>
                                                setPanel(open ? 'travel' : null)
                                            }
                                            title={
                                                travel.arrangement
                                                    ? 'Edit travel and stay'
                                                    : 'Add travel and stay'
                                            }
                                            description="Setting the status to Confirmed prepares a message with these details for the patient."
                                        >
                                            <form
                                                className="space-y-4"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    travelForm.transform(
                                                        (data) => ({
                                                            ...data,
                                                            assigned_coordinator_id:
                                                                data.assigned_coordinator_id
                                                                    ? Number(
                                                                          data.assigned_coordinator_id,
                                                                      )
                                                                    : null,
                                                            expected_arrival_date:
                                                                data.expected_arrival_date ||
                                                                null,
                                                            expected_departure_date:
                                                                data.expected_departure_date ||
                                                                null,
                                                            flight_information:
                                                                data.flight_information ||
                                                                null,
                                                            airport_pickup_details:
                                                                data.airport_pickup_details ||
                                                                null,
                                                            transfer_arrangements:
                                                                data.transfer_arrangements ||
                                                                null,
                                                            accommodation:
                                                                data.accommodation ||
                                                                null,
                                                            hotel_details:
                                                                data.hotel_details ||
                                                                null,
                                                            special_administrative_requirements:
                                                                data.special_administrative_requirements ||
                                                                null,
                                                            notes:
                                                                data.notes ||
                                                                null,
                                                        }),
                                                    );
                                                    travelForm.put(
                                                        travel.urls.upsert,
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () =>
                                                                setPanel(null),
                                                        },
                                                    );
                                                }}
                                            >
                                                <div className="grid gap-3 lg:grid-cols-2">
                                                    <div className="grid gap-2">
                                                        <Label>Status</Label>
                                                        <Select
                                                            value={
                                                                travelForm.data
                                                                    .status
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                travelForm.setData(
                                                                    'status',
                                                                    value,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder="Status" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {travel.status_options.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option.value
                                                                            }
                                                                            value={
                                                                                option.value
                                                                            }
                                                                        >
                                                                            {
                                                                                option.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>
                                                            Visa assistance
                                                        </Label>
                                                        <Select
                                                            value={
                                                                travelForm.data
                                                                    .visa_assistance_status
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                travelForm.setData(
                                                                    'visa_assistance_status',
                                                                    value,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue placeholder="Visa assistance" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {travel.visa_options.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option.value
                                                                            }
                                                                            value={
                                                                                option.value
                                                                            }
                                                                        >
                                                                            {
                                                                                option.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>
                                                            Expected arrival
                                                        </Label>
                                                        <Input
                                                            type="date"
                                                            value={
                                                                travelForm.data
                                                                    .expected_arrival_date
                                                            }
                                                            onChange={(event) =>
                                                                travelForm.setData(
                                                                    'expected_arrival_date',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>
                                                            Expected departure
                                                        </Label>
                                                        <Input
                                                            type="date"
                                                            value={
                                                                travelForm.data
                                                                    .expected_departure_date
                                                            }
                                                            onChange={(event) =>
                                                                travelForm.setData(
                                                                    'expected_departure_date',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>
                                                            Flight information
                                                            (no passport data)
                                                        </Label>
                                                        <Textarea
                                                            className="min-h-20"
                                                            value={
                                                                travelForm.data
                                                                    .flight_information
                                                            }
                                                            onChange={(event) =>
                                                                travelForm.setData(
                                                                    'flight_information',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-3 lg:col-span-2">
                                                        <ToggleField
                                                            id="travel-airport-pickup"
                                                            label="Airport pickup needed"
                                                            description="Arrange a transfer from the airport when the patient lands."
                                                            checked={
                                                                travelForm.data
                                                                    .airport_pickup_required
                                                            }
                                                            onChange={(value) =>
                                                                travelForm.setData(
                                                                    'airport_pickup_required',
                                                                    value,
                                                                )
                                                            }
                                                        />
                                                        {travelForm.data
                                                            .airport_pickup_required ? (
                                                            <div className="grid gap-2">
                                                                <Label htmlFor="travel-pickup-details">
                                                                    Pickup
                                                                    details
                                                                </Label>
                                                                <Input
                                                                    id="travel-pickup-details"
                                                                    placeholder="Driver, meeting point, flight number to watch…"
                                                                    value={
                                                                        travelForm
                                                                            .data
                                                                            .airport_pickup_details
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        travelForm.setData(
                                                                            'airport_pickup_details',
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                    <div className="grid gap-2 lg:col-span-2">
                                                        <Label>
                                                            Hotel /
                                                            accommodation
                                                        </Label>
                                                        <Input
                                                            value={
                                                                travelForm.data
                                                                    .hotel_details
                                                            }
                                                            onChange={(event) =>
                                                                travelForm.setData(
                                                                    'hotel_details',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                                <InputError
                                                    message={
                                                        travelForm.errors.travel
                                                    }
                                                />
                                                <Button
                                                    type="submit"
                                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                                    disabled={
                                                        travelForm.processing
                                                    }
                                                >
                                                    Save travel details
                                                </Button>
                                            </form>
                                        </FormSheet>
                                    ) : null}
                                </Card>
                            ) : null}
                        </>
                    )}

                    {tab === 'tasks' && (
                        <>
                            {tasks || followUps ? (
                                <section className="grid gap-4 lg:grid-cols-2">
                                    {tasks ? (
                                        <Card
                                            title="Tasks"
                                            icon={ListChecks}
                                            action={
                                                permissions.canManageTasks ? (
                                                    <NewButton
                                                        label="New task"
                                                        onClick={() =>
                                                            setPanel('task')
                                                        }
                                                    />
                                                ) : undefined
                                            }
                                        >
                                            {tasks.items.length === 0 ? (
                                                <EmptyRecords text="No tasks on this case." />
                                            ) : (
                                                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200">
                                                    {tasks.items.map((task) => {
                                                        const finished = [
                                                            'completed',
                                                            'cancelled',
                                                        ].includes(task.status);
                                                        const overdue =
                                                            !finished &&
                                                            task.due_at !==
                                                                null &&
                                                            new Date(
                                                                task.due_at,
                                                            ) < new Date();

                                                        return (
                                                            <li
                                                                key={task.uuid}
                                                                className="flex items-start gap-3 px-4 py-3 text-sm"
                                                            >
                                                                <button
                                                                    type="button"
                                                                    className={cn(
                                                                        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border transition',
                                                                        finished
                                                                            ? 'border-brand bg-brand text-white'
                                                                            : 'border-slate-300 bg-white hover:border-brand hover:bg-brand/10',
                                                                    )}
                                                                    disabled={
                                                                        finished ||
                                                                        !permissions.canManageTasks ||
                                                                        taskTransitionForm.processing
                                                                    }
                                                                    aria-label={
                                                                        finished
                                                                            ? `${task.title} is done`
                                                                            : `Mark ${task.title} as done`
                                                                    }
                                                                    onClick={() =>
                                                                        runTaskTransition(
                                                                            task.transition_url,
                                                                            'completed',
                                                                        )
                                                                    }
                                                                >
                                                                    {finished ? (
                                                                        <Check className="size-3" />
                                                                    ) : null}
                                                                </button>
                                                                <div className="min-w-0 flex-1">
                                                                    <p
                                                                        className={cn(
                                                                            'font-medium',
                                                                            finished
                                                                                ? 'text-slate-400 line-through'
                                                                                : 'text-slate-900',
                                                                        )}
                                                                    >
                                                                        {
                                                                            task.title
                                                                        }
                                                                    </p>
                                                                    <p className="mt-0.5 text-xs text-slate-500">
                                                                        {task.assigned_user
                                                                            ? task
                                                                                  .assigned_user
                                                                                  .name
                                                                            : 'Unassigned'}
                                                                        {task.due_at ? (
                                                                            <span
                                                                                className={
                                                                                    overdue
                                                                                        ? 'font-medium text-rose-700'
                                                                                        : ''
                                                                                }
                                                                            >
                                                                                {` · ${overdue ? 'Overdue since' : 'Due'} ${new Date(task.due_at).toLocaleDateString([], { dateStyle: 'medium' })}`}
                                                                            </span>
                                                                        ) : null}
                                                                        {task.status ===
                                                                        'in_progress'
                                                                            ? ' · In progress'
                                                                            : ''}
                                                                        {task.status ===
                                                                        'waiting'
                                                                            ? ' · Waiting'
                                                                            : ''}
                                                                    </p>
                                                                </div>
                                                                {[
                                                                    'high',
                                                                    'urgent',
                                                                ].includes(
                                                                    task.priority,
                                                                ) &&
                                                                !finished ? (
                                                                    <span className="shrink-0 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">
                                                                        {
                                                                            task.priority_label
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                                {permissions.canManageTasks &&
                                                                task.status ===
                                                                    'open' ? (
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        className="h-7 shrink-0 rounded-full px-2.5 text-xs text-slate-600"
                                                                        disabled={
                                                                            taskTransitionForm.processing
                                                                        }
                                                                        onClick={() =>
                                                                            runTaskTransition(
                                                                                task.transition_url,
                                                                                'in_progress',
                                                                            )
                                                                        }
                                                                    >
                                                                        Start
                                                                    </Button>
                                                                ) : null}
                                                            </li>
                                                        );
                                                    })}
                                                </ul>
                                            )}
                                            {permissions.canManageTasks ? (
                                                <FormSheet
                                                    open={panel === 'task'}
                                                    onOpenChange={(open) =>
                                                        setPanel(
                                                            open
                                                                ? 'task'
                                                                : null,
                                                        )
                                                    }
                                                    title="New task"
                                                    description="Something someone on the team needs to do for this case."
                                                >
                                                    <form
                                                        className="space-y-4"
                                                        onSubmit={(event) => {
                                                            event.preventDefault();
                                                            taskForm.transform(
                                                                (data) => ({
                                                                    title: data.title,
                                                                    description:
                                                                        data.description ||
                                                                        null,
                                                                    assigned_user_id:
                                                                        data.assigned_user_id &&
                                                                        data.assigned_user_id !==
                                                                            UNASSIGNED
                                                                            ? Number(
                                                                                  data.assigned_user_id,
                                                                              )
                                                                            : null,
                                                                    priority:
                                                                        data.priority,
                                                                    due_at:
                                                                        data.due_at ||
                                                                        null,
                                                                }),
                                                            );
                                                            taskForm.post(
                                                                tasks.urls
                                                                    .store,
                                                                {
                                                                    preserveScroll: true,
                                                                    onSuccess:
                                                                        () => {
                                                                            setPanel(
                                                                                null,
                                                                            );
                                                                            taskForm.reset();
                                                                        },
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        <div className="grid gap-3 lg:grid-cols-2">
                                                            <div className="grid gap-2 lg:col-span-2">
                                                                <Label>
                                                                    Title
                                                                </Label>
                                                                <Input
                                                                    value={
                                                                        taskForm
                                                                            .data
                                                                            .title
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        taskForm.setData(
                                                                            'title',
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Priority
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        taskForm
                                                                            .data
                                                                            .priority
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        taskForm.setData(
                                                                            'priority',
                                                                            value,
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue placeholder="Priority" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {tasks.priority_options.map(
                                                                            (
                                                                                option,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        option.value
                                                                                    }
                                                                                    value={
                                                                                        option.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        option.label
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Assignee
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        taskForm
                                                                            .data
                                                                            .assigned_user_id ||
                                                                        UNASSIGNED
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        taskForm.setData(
                                                                            'assigned_user_id',
                                                                            value ===
                                                                                UNASSIGNED
                                                                                ? ''
                                                                                : value,
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue placeholder="Unassigned" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem
                                                                            value={
                                                                                UNASSIGNED
                                                                            }
                                                                        >
                                                                            Unassigned
                                                                        </SelectItem>
                                                                        {assignableMembers.staff.map(
                                                                            (
                                                                                member,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        member.id
                                                                                    }
                                                                                    value={String(
                                                                                        member.id,
                                                                                    )}
                                                                                >
                                                                                    {
                                                                                        member.name
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                        </div>
                                                        <InputError
                                                            message={
                                                                taskForm.errors
                                                                    .title ||
                                                                taskForm.errors
                                                                    .task
                                                            }
                                                        />
                                                        <Button
                                                            type="submit"
                                                            className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                                            disabled={
                                                                taskForm.processing
                                                            }
                                                        >
                                                            Save task
                                                        </Button>
                                                    </form>
                                                </FormSheet>
                                            ) : null}
                                        </Card>
                                    ) : null}

                                    {followUps ? (
                                        <Card
                                            title="Follow-ups"
                                            icon={CalendarDays}
                                            action={
                                                permissions.canManageTasks ? (
                                                    <NewButton
                                                        label="Schedule follow-up"
                                                        onClick={() =>
                                                            setPanel(
                                                                'follow_up',
                                                            )
                                                        }
                                                    />
                                                ) : undefined
                                            }
                                        >
                                            {followUps.items.length === 0 ? (
                                                <EmptyRecords text="No follow-ups scheduled. Add one after treatment to check in with the patient." />
                                            ) : (
                                                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-200">
                                                    {followUps.items.map(
                                                        (followUp) => {
                                                            const finished = [
                                                                'completed',
                                                                'cancelled',
                                                            ].includes(
                                                                followUp.status,
                                                            );

                                                            return (
                                                                <li
                                                                    key={
                                                                        followUp.uuid
                                                                    }
                                                                    className="space-y-1.5 px-4 py-3 text-sm"
                                                                >
                                                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                                                        <div className="min-w-0">
                                                                            <p
                                                                                className={cn(
                                                                                    'font-medium',
                                                                                    finished
                                                                                        ? 'text-slate-400'
                                                                                        : 'text-slate-900',
                                                                                )}
                                                                            >
                                                                                {
                                                                                    followUp.title
                                                                                }
                                                                            </p>
                                                                            <p className="mt-0.5 text-xs text-slate-500">
                                                                                {
                                                                                    followUp.follow_up_type_label
                                                                                }
                                                                                {
                                                                                    ' · '
                                                                                }
                                                                                {followUp.assigned_user
                                                                                    ? followUp
                                                                                          .assigned_user
                                                                                          .name
                                                                                    : 'Unassigned'}
                                                                                {followUp.due_at ? (
                                                                                    <span
                                                                                        className={
                                                                                            followUp.is_overdue
                                                                                                ? 'font-medium text-rose-700'
                                                                                                : ''
                                                                                        }
                                                                                    >
                                                                                        {` · ${followUp.is_overdue ? 'Overdue since' : 'Due'} ${new Date(followUp.due_at).toLocaleDateString([], { dateStyle: 'medium' })}`}
                                                                                    </span>
                                                                                ) : null}
                                                                            </p>
                                                                        </div>
                                                                        {finished ? (
                                                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                                                                {
                                                                                    followUp.status_label
                                                                                }
                                                                            </span>
                                                                        ) : permissions.canManageTasks ? (
                                                                            <div className="flex gap-1.5">
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    variant="ghost"
                                                                                    className="h-7 rounded-full px-2.5 text-xs text-slate-600"
                                                                                    disabled={
                                                                                        followUpCompleteForm.processing
                                                                                    }
                                                                                    onClick={() =>
                                                                                        runFollowUpComplete(
                                                                                            followUp.complete_url,
                                                                                            true,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Cancel
                                                                                </Button>
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    className="h-7 rounded-full bg-brand px-3 text-xs text-white hover:bg-brand/90"
                                                                                    disabled={
                                                                                        followUpCompleteForm.processing
                                                                                    }
                                                                                    onClick={() =>
                                                                                        runFollowUpComplete(
                                                                                            followUp.complete_url,
                                                                                            false,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Check className="size-3.5" />
                                                                                    Done
                                                                                </Button>
                                                                            </div>
                                                                        ) : null}
                                                                    </div>
                                                                    {followUp.outcome_notes ? (
                                                                        <p className="text-xs text-slate-600">
                                                                            Outcome:{' '}
                                                                            {
                                                                                followUp.outcome_notes
                                                                            }
                                                                        </p>
                                                                    ) : null}
                                                                </li>
                                                            );
                                                        },
                                                    )}
                                                </ul>
                                            )}
                                            {permissions.canManageTasks ? (
                                                <FormSheet
                                                    open={panel === 'follow_up'}
                                                    onOpenChange={(open) =>
                                                        setPanel(
                                                            open
                                                                ? 'follow_up'
                                                                : null,
                                                        )
                                                    }
                                                    title="Schedule a follow-up"
                                                    description="A check-in with the patient after treatment, such as a review call or travel return."
                                                >
                                                    <form
                                                        className="space-y-4"
                                                        onSubmit={(event) => {
                                                            event.preventDefault();
                                                            followUpForm.transform(
                                                                (data) => ({
                                                                    title: data.title,
                                                                    description:
                                                                        data.description ||
                                                                        null,
                                                                    follow_up_type:
                                                                        data.follow_up_type,
                                                                    due_at: data.due_at,
                                                                    assigned_user_id:
                                                                        data.assigned_user_id &&
                                                                        data.assigned_user_id !==
                                                                            UNASSIGNED
                                                                            ? Number(
                                                                                  data.assigned_user_id,
                                                                              )
                                                                            : null,
                                                                }),
                                                            );
                                                            followUpForm.post(
                                                                followUps.urls
                                                                    .store,
                                                                {
                                                                    preserveScroll: true,
                                                                    onSuccess:
                                                                        () => {
                                                                            setPanel(
                                                                                null,
                                                                            );
                                                                            followUpForm.reset();
                                                                        },
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        <div className="grid gap-3 lg:grid-cols-2">
                                                            <div className="grid gap-2 lg:col-span-2">
                                                                <Label>
                                                                    Title
                                                                </Label>
                                                                <Input
                                                                    value={
                                                                        followUpForm
                                                                            .data
                                                                            .title
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        followUpForm.setData(
                                                                            'title',
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Type
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        followUpForm
                                                                            .data
                                                                            .follow_up_type
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        followUpForm.setData(
                                                                            'follow_up_type',
                                                                            value,
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue placeholder="Type" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {followUps.type_options.map(
                                                                            (
                                                                                option,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        option.value
                                                                                    }
                                                                                    value={
                                                                                        option.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        option.label
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Due
                                                                </Label>
                                                                <Input
                                                                    type="datetime-local"
                                                                    value={
                                                                        followUpForm
                                                                            .data
                                                                            .due_at
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        followUpForm.setData(
                                                                            'due_at',
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="grid gap-2 lg:col-span-2">
                                                                <Label>
                                                                    Assignee
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        followUpForm
                                                                            .data
                                                                            .assigned_user_id ||
                                                                        UNASSIGNED
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        followUpForm.setData(
                                                                            'assigned_user_id',
                                                                            value ===
                                                                                UNASSIGNED
                                                                                ? ''
                                                                                : value,
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue placeholder="Case coordinator (default)" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem
                                                                            value={
                                                                                UNASSIGNED
                                                                            }
                                                                        >
                                                                            Case
                                                                            coordinator
                                                                            (default)
                                                                        </SelectItem>
                                                                        {assignableMembers.staff.map(
                                                                            (
                                                                                member,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        member.id
                                                                                    }
                                                                                    value={String(
                                                                                        member.id,
                                                                                    )}
                                                                                >
                                                                                    {
                                                                                        member.name
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>
                                                        </div>
                                                        <InputError
                                                            message={
                                                                followUpForm
                                                                    .errors
                                                                    .title ||
                                                                followUpForm
                                                                    .errors
                                                                    .due_at ||
                                                                followUpForm
                                                                    .errors
                                                                    .follow_up
                                                            }
                                                        />
                                                        <Button
                                                            type="submit"
                                                            className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                                            disabled={
                                                                followUpForm.processing
                                                            }
                                                        >
                                                            Save follow-up
                                                        </Button>
                                                    </form>
                                                </FormSheet>
                                            ) : null}
                                        </Card>
                                    ) : null}
                                </section>
                            ) : null}
                        </>
                    )}

                    {tab === 'communications' && (
                        <>
                            {communications ? (
                                <CaseConversation
                                    patientName={patientCase.patient.full_name}
                                    patientEmail={communications.patient_email}
                                    messages={communications.items}
                                    storeUrl={communications.urls.store}
                                    canManage={
                                        permissions.canManageCommunications
                                    }
                                    aiDraftingAvailable={
                                        communications.ai_drafting_available
                                    }
                                />
                            ) : null}
                        </>
                    )}

                    {tab === 'overview' && (
                        <>
                            <Card
                                title="Activity timeline"
                                description="Recent case events"
                                icon={Activity}
                            >
                                {patientCase.activities.length === 0 ? (
                                    <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center text-sm text-slate-500">
                                        No activity recorded yet.
                                    </div>
                                ) : (
                                    <ol className="space-y-3">
                                        {patientCase.activities.map(
                                            (activity) => (
                                                <li
                                                    key={activity.id}
                                                    className="relative rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 ps-5"
                                                >
                                                    <span className="absolute top-4 bottom-4 left-0 w-1 rounded-full bg-brand/70" />
                                                    <div className="text-sm font-medium text-slate-900">
                                                        {activity.summary}
                                                    </div>
                                                    <div className="mt-1 text-xs text-slate-500">
                                                        {activity.type_label}
                                                        {activity.actor
                                                            ? ` · ${activity.actor}`
                                                            : ' · System'}
                                                        {' · '}
                                                        {new Date(
                                                            activity.occurred_at,
                                                        ).toLocaleString()}
                                                        {activity.is_patient_visible
                                                            ? ' · Patient visible'
                                                            : ''}
                                                    </div>
                                                </li>
                                            ),
                                        )}
                                    </ol>
                                )}
                            </Card>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

function Card({
    title,
    description,
    icon: Icon,
    action,
    note,
    children,
}: {
    title: string;
    description?: string;
    icon?: typeof UserRound;
    /** A button shown on the right of the header, such as "New proposal". */
    action?: ReactNode;
    /** A short rule or reminder, shown as a quiet line instead of a warning. */
    note?: string;
    children: ReactNode;
}) {
    return (
        <section className="light-surface rounded-2xl border border-slate-200/80 bg-white shadow-[0_12px_40px_-28px_rgba(15,23,42,0.35)]">
            <div className="flex flex-wrap items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
                {Icon ? (
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                        <Icon className="size-3.5" strokeWidth={1.75} />
                    </span>
                ) : null}
                <div className="min-w-0 flex-1">
                    <h2 className="text-sm font-semibold text-slate-900">
                        {title}
                    </h2>
                    {description ? (
                        <p className="text-xs text-slate-500">{description}</p>
                    ) : null}
                </div>
                {action}
            </div>
            <div className="space-y-3 p-5">
                {note ? (
                    <p className="flex items-start gap-1.5 text-xs text-slate-500">
                        <Info className="mt-px size-3.5 shrink-0" />
                        {note}
                    </p>
                ) : null}
                {children}
            </div>
        </section>
    );
}

/** The "+ New …" button used in card headers to open a side panel. */
function NewButton({
    label,
    onClick,
    icon: Icon = Plus,
}: {
    label: string;
    onClick: () => void;
    icon?: LucideIcon;
}) {
    return (
        <Button
            type="button"
            size="sm"
            className="rounded-full bg-brand text-white hover:bg-brand/90"
            onClick={onClick}
        >
            <Icon className="size-4" />
            {label}
        </Button>
    );
}

/** A record row: a soft bordered block used for proposals, appointments, tasks and follow-ups. */
function RecordRow({
    children,
    highlight = false,
}: {
    children: ReactNode;
    highlight?: boolean;
}) {
    return (
        <li
            className={cn(
                'space-y-3 rounded-xl border px-4 py-3.5 text-sm',
                highlight
                    ? 'border-brand/30 bg-brand/[0.04]'
                    : 'border-slate-200 bg-white',
            )}
        >
            {children}
        </li>
    );
}

function EmptyRecords({ text }: { text: string }) {
    return (
        <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-8 text-center text-sm text-slate-500">
            {text}
        </div>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label className="text-sm font-medium text-slate-700">
                {label}
            </Label>
            {children}
        </div>
    );
}

function Detail({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: string | null | undefined;
    icon?: typeof Mail;
}) {
    return (
        <div className="min-w-0">
            <div className="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                {Icon ? <Icon className="size-3.5" /> : null}
                {label}
            </div>
            <div className="mt-1 text-sm font-medium break-words text-slate-900">
                {value || '—'}
            </div>
        </div>
    );
}

CaseShow.layout = (props: {
    team: { slug: string };
    case: { reference: string };
}) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard(props.team.slug) },
        { title: 'Cases', href: `/${props.team.slug}/cases` },
        {
            title: props.case.reference,
            href: `/${props.team.slug}/cases/${props.case.reference}`,
        },
    ],
});
