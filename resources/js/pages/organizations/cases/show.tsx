import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    CalendarCheck,
    CalendarDays,
    Download,
    Eye,
    FileText,
    Globe2,
    History,
    Languages,
    LayoutDashboard,
    ListChecks,
    Mail,
    MessageSquare,
    Phone,
    Plane,
    ScrollText,
    Stethoscope,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import PageTabs from '@/components/page-tabs';
import { RowAction, RowActions } from '@/components/row-actions';
import { ToggleField } from '@/components/toggle-field';
import { Badge } from '@/components/ui/badge';
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
            ai_state: 'processing' | 'analysed' | 'unavailable' | 'not_analysed';
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
            items: Array<{
                uuid: string;
                channel: string;
                channel_label: string;
                direction: string;
                status: string;
                status_label: string;
                language: string;
                subject: string | null;
                body: string;
                recipient: string | null;
                is_ai_draft: boolean;
                requires_approval: boolean;
                created_by: string | null;
                approved_by: string | null;
                created_at: string | null;
                approve_url: string;
            }>;
            channel_options: Array<{ value: string; label: string }>;
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
    | 'travel'
    | 'appointments'
    | 'tasks'
    | 'timeline';

const TAB_ICONS: Record<CaseTab, LucideIcon> = {
    overview: LayoutDashboard,
    documents: FileText,
    evaluation: Stethoscope,
    communications: MessageSquare,
    proposal: ScrollText,
    travel: Plane,
    appointments: CalendarCheck,
    tasks: ListChecks,
    timeline: History,
};

const NEXT_STEPS: Record<
    string,
    { text: string; tab: CaseTab; cta: string }
> = {
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
        text: 'Prepare the administrative treatment proposal.',
        tab: 'proposal',
        cta: 'Open proposal',
    },
    proposal_sent: {
        text: 'Proposal sent. Waiting for the patient to respond.',
        tab: 'communications',
        cta: 'View messages',
    },
    awaiting_patient_response: {
        text: 'Waiting for the patient to respond. Follow up if needed.',
        tab: 'communications',
        cta: 'Contact patient',
    },
    appointment_planning: {
        text: 'Propose and confirm an appointment with the hospital.',
        tab: 'appointments',
        cta: 'Plan appointment',
    },
    travel_coordination: {
        text: 'Record the patient travel and accommodation details.',
        tab: 'travel',
        cta: 'Open travel',
    },
    treatment_scheduled: {
        text: 'Treatment is scheduled. Confirm travel arrangements are complete.',
        tab: 'travel',
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

    const communicationForm = useForm({
        channel: 'internal',
        body: '',
        subject: '',
        recipient: '',
        log_only: true,
        draft_with_ai: false,
        ai_brief: '',
    });

    const communicationApproveForm = useForm({
        mark_sent: false,
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

    const visibleTabs: Array<{
        id: CaseTab;
        label: string;
        count?: number;
    }> = [
        { id: 'overview', label: 'Overview' },
        {
            id: 'documents',
            label: 'Documents',
            count: patientCase.documents.length,
        },
        ...(permissions.canViewClinical && clinical
            ? [{ id: 'evaluation' as CaseTab, label: 'Doctor evaluation' }]
            : []),
        ...(communications
            ? [
                  {
                      id: 'communications' as CaseTab,
                      label: 'Communications',
                      count: communications.items.length,
                  },
              ]
            : []),
        ...(proposals
            ? [{ id: 'proposal' as CaseTab, label: 'Treatment proposal' }]
            : []),
        ...(travel ? [{ id: 'travel' as CaseTab, label: 'Travel' }] : []),
        ...(appointments
            ? [{ id: 'appointments' as CaseTab, label: 'Appointments' }]
            : []),
        ...(tasks || followUps
            ? [
                  {
                      id: 'tasks' as CaseTab,
                      label: 'Tasks & follow-ups',
                      count: openTaskCount,
                  },
              ]
            : []),
        { id: 'timeline', label: 'Timeline' },
    ];

    const [selectedTab, setSelectedTab] = useState<string>(() =>
        typeof window === 'undefined'
            ? 'overview'
            : (new URLSearchParams(window.location.search).get('tab') ??
              'overview'),
    );
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

    const baseNextStep = NEXT_STEPS[patientCase.status] ?? null;
    const nextStep =
        baseNextStep && !visibleTabs.some((item) => item.id === baseNextStep.tab)
            ? { ...baseNextStep, tab: 'overview' as CaseTab, cta: 'Open overview' }
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
                                        <Badge variant="outline">Demo</Badge>
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

                {tab === 'overview' && (<>
                {(permissions.canUpdateStatus ||
                    permissions.canAssignCases) && (
                    <section className="grid gap-4 lg:grid-cols-2">
                        {permissions.canUpdateStatus ? (
                            <Card title="Update status" icon={Activity}>
                                <form
                                    className="space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        statusForm.patch(
                                            `/${team.slug}/cases/${patientCase.reference}/status`,
                                            { preserveScroll: true },
                                        );
                                    }}
                                >
                                    <Field label="Next status">
                                        <Select
                                            value={
                                                statusForm.data.status ||
                                                undefined
                                            }
                                            onValueChange={(value) =>
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
                                                            key={status.value}
                                                            value={status.value}
                                                        >
                                                            {status.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={statusForm.errors.status}
                                        />
                                    </Field>
                                    <Field label="Note (optional)">
                                        <Input
                                            value={statusForm.data.note}
                                            onChange={(event) =>
                                                statusForm.setData(
                                                    'note',
                                                    event.target.value,
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
                            <Card title="Assignment" icon={UserRound}>
                                <form
                                    className="space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        assignmentForm.transform((data) => ({
                                            coordinator_id: data.coordinator_id
                                                ? Number(data.coordinator_id)
                                                : null,
                                            doctor_id: data.doctor_id
                                                ? Number(data.doctor_id)
                                                : null,
                                            clear_coordinator:
                                                !data.coordinator_id,
                                            clear_doctor: !data.doctor_id,
                                        }));
                                        assignmentForm.patch(
                                            `/${team.slug}/cases/${patientCase.reference}/assignment`,
                                            { preserveScroll: true },
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
                                            onValueChange={(value) =>
                                                assignmentForm.setData(
                                                    'coordinator_id',
                                                    value === UNASSIGNED
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Unassigned" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={UNASSIGNED}>
                                                    Unassigned
                                                </SelectItem>
                                                {assignableMembers.coordinators.map(
                                                    (member) => (
                                                        <SelectItem
                                                            key={member.id}
                                                            value={String(
                                                                member.id,
                                                            )}
                                                        >
                                                            {member.name} (
                                                            {member.role_label})
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    <Field label="Doctor">
                                        <Select
                                            value={
                                                assignmentForm.data.doctor_id ||
                                                UNASSIGNED
                                            }
                                            onValueChange={(value) =>
                                                assignmentForm.setData(
                                                    'doctor_id',
                                                    value === UNASSIGNED
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Unassigned" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={UNASSIGNED}>
                                                    Unassigned
                                                </SelectItem>
                                                {assignableMembers.doctors.map(
                                                    (member) => (
                                                        <SelectItem
                                                            key={member.id}
                                                            value={String(
                                                                member.id,
                                                            )}
                                                        >
                                                            {member.name} (
                                                            {member.role_label})
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    <InputError
                                        message={
                                            assignmentForm.errors.assignment
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        className="h-11 w-full rounded-full bg-brand text-white hover:bg-brand/90"
                                        disabled={assignmentForm.processing}
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
                                value={patientCase.patient.date_of_birth}
                            />
                            <Detail
                                label="Gender"
                                value={patientCase.patient.gender}
                            />
                            <Detail
                                icon={Globe2}
                                label="Country"
                                value={
                                    patientCase.patient.country_of_residence
                                }
                            />
                            <Detail
                                icon={Languages}
                                label="Language"
                                value={
                                    patientCase.patient.preferred_language
                                }
                            />
                        </dl>
                    </Card>

                    <Card title="Enquiry" icon={Stethoscope}>
                        <div className="space-y-4">
                            <Detail
                                label="Reason"
                                value={
                                    patientCase.enquiry.reason_for_contact
                                }
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Detail
                                    label="Diagnosis"
                                    value={patientCase.enquiry.diagnosis}
                                />
                                <Detail
                                    label="Allergies"
                                    value={
                                        patientCase.enquiry.known_allergies
                                    }
                                />
                            </div>
                            <Detail
                                label="Symptoms / history"
                                value={
                                    patientCase.enquiry.symptoms_or_history
                                }
                            />
                            <Detail
                                label="Medications"
                                value={
                                    patientCase.enquiry.current_medications
                                }
                            />
                            <Detail
                                label="Department"
                                value={patientCase.enquiry.department}
                            />
                        </div>
                    </Card>
                </section>
                </>)}

                {tab === 'documents' && (<>
                <Card
                    title="Documents"
                    description={`${patientCase.documents.length} uploaded`}
                    icon={FileText}
                >
                    {patientCase.status === 'awaiting_coordinator_review' &&
                    permissions.canUpdateStatus ? (
                        <div className="space-y-3 rounded-xl border border-brand/20 bg-brand/5 px-4 py-4">
                            <div>
                                <p className="text-sm font-semibold text-slate-900">
                                    Ready for doctor review?
                                </p>
                                <p className="text-xs text-slate-500">
                                    Check the AI drafts, make sure a doctor is
                                    assigned, then send the case on.
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
                                        {documentsReviewed ? '✓' : '○'}
                                    </span>
                                    {patientCase.documents.length === 0
                                        ? 'No documents uploaded'
                                        : unreviewedDocumentCount === 0
                                          ? 'All AI drafts have been reviewed'
                                          : `${unreviewedDocumentCount} AI ${unreviewedDocumentCount === 1 ? 'draft needs' : 'drafts need'} review`}
                                </li>
                                <li className="flex items-center gap-2">
                                    <span
                                        className={
                                            patientCase.assignments.doctor
                                                ? 'text-emerald-600'
                                                : 'text-amber-600'
                                        }
                                        aria-hidden="true"
                                    >
                                        {patientCase.assignments.doctor
                                            ? '✓'
                                            : '○'}
                                    </span>
                                    {patientCase.assignments.doctor
                                        ? `Doctor assigned: ${patientCase.assignments.doctor.name}`
                                        : 'No doctor assigned yet'}
                                    {!patientCase.assignments.doctor &&
                                    permissions.canAssignCases ? (
                                        <button
                                            type="button"
                                            className="text-brand underline-offset-2 hover:underline"
                                            onClick={() => selectTab('overview')}
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
                                                { preserveScroll: true },
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
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Request more information
                                    </Button>
                                ) : null}
                            </div>
                            {!readyForDoctor && canSendToDoctor ? (
                                <p className="text-xs text-slate-500">
                                    Complete the checks above to enable sending
                                    to a doctor.
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
                                an AI draft waiting for review. Drafts are not
                                clinically approved.
                            </span>
                            <Button asChild size="sm" className="rounded-full">
                                <Link href={firstUnreviewedDocument.review_url}>
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
                            {patientCase.documents.map((document) => (
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
                                                href={document.review_url}
                                                className="block truncate font-medium text-slate-900 hover:text-brand"
                                            >
                                                {document.original_filename}
                                            </Link>
                                            <div className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                                <span>{document.category}</span>
                                                <span aria-hidden="true">·</span>
                                                <span>
                                                    {Math.round(document.size_bytes / 1024)} KB
                                                </span>
                                                {document.ai_state === 'processing' ? (
                                                    <span className="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 font-semibold text-sky-800">
                                                        AI processing
                                                    </span>
                                                ) : null}
                                                {document.ai_state === 'unavailable' ? (
                                                    <span className="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">
                                                        AI off · review the original
                                                    </span>
                                                ) : null}
                                                {document.ai_state === 'not_analysed' ? (
                                                    <span className="rounded-full border border-brand/30 bg-brand/5 px-2 py-0.5 font-semibold text-brand">
                                                        Not analysed yet
                                                    </span>
                                                ) : null}
                                                {document.ai_review === 'unreviewed' ? (
                                                    <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-semibold text-amber-800">
                                                        AI draft · needs review
                                                    </span>
                                                ) : null}
                                                {document.ai_review === 'reviewed' ? (
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
                                            href={document.review_url}
                                            tone="primary"
                                            showLabel
                                        />
                                        {document.download_url ? (
                                            <RowAction
                                                label="Download original"
                                                icon={Download}
                                                nativeHref={document.download_url}
                                            />
                                        ) : null}
                                    </RowActions>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
                </>)}

                {tab === 'evaluation' && (<>
                {permissions.canViewClinical && clinical ? (
                    <Card title="Clinical review">
                        <p className="text-sm text-amber-800">
                            {clinical.disclaimer}
                        </p>

                        {clinical.reviews.length > 0 ? (
                            <div className="space-y-3">
                                <h3 className="text-sm font-medium">
                                    Review history
                                </h3>
                                <ul className="space-y-3">
                                    {clinical.reviews.map((review) => (
                                        <li
                                            key={review.uuid}
                                            className="rounded-lg border p-3 text-sm"
                                        >
                                            <div className="flex flex-wrap gap-2">
                                                <Badge>
                                                    {review.status_label}
                                                </Badge>
                                                {review.outcome_label ? (
                                                    <Badge variant="secondary">
                                                        {review.outcome_label}
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <p className="mt-2 text-muted-foreground">
                                                {review.reviewer?.name ??
                                                    'Unknown reviewer'}
                                                {' · '}
                                                {new Date(
                                                    review.started_at,
                                                ).toLocaleString()}
                                            </p>
                                            {review.findings ? (
                                                <p className="mt-2 whitespace-pre-wrap">
                                                    {review.findings}
                                                </p>
                                            ) : null}
                                            {review.additional_information_request ? (
                                                <p className="mt-2 whitespace-pre-wrap text-amber-800">
                                                    Additional info requested:{' '}
                                                    {
                                                        review.additional_information_request
                                                    }
                                                </p>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No doctor reviews submitted yet.
                            </p>
                        )}

                        {clinical.observations.length > 0 ? (
                            <div className="space-y-3">
                                <h3 className="text-sm font-medium">
                                    Clinical observations
                                </h3>
                                <ul className="space-y-3">
                                    {clinical.observations.map(
                                        (observation) => (
                                            <li
                                                key={observation.id}
                                                className="border-b pb-3 text-sm last:border-0"
                                            >
                                                <p className="whitespace-pre-wrap">
                                                    {observation.body}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {observation.author ??
                                                        'Unknown'}
                                                    {' · '}
                                                    {observation.visibility}
                                                    {' · '}
                                                    {new Date(
                                                        observation.observed_at,
                                                    ).toLocaleString()}
                                                </p>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        ) : null}

                        {clinical.recommendations.length > 0 ? (
                            <div className="space-y-3">
                                <h3 className="text-sm font-medium">
                                    Recommendations
                                </h3>
                                <ul className="space-y-4">
                                    {clinical.recommendations.map(
                                        (recommendation) => (
                                            <li
                                                key={recommendation.uuid}
                                                className="rounded-lg border p-3 text-sm"
                                            >
                                                <div className="flex flex-wrap gap-2">
                                                    <Badge>
                                                        {
                                                            recommendation.status_label
                                                        }
                                                    </Badge>
                                                    <Badge variant="outline">
                                                        v
                                                        {
                                                            recommendation.version
                                                        }
                                                    </Badge>
                                                    {recommendation.is_ai_generated ? (
                                                        <Badge variant="secondary">
                                                            AI — not approvable
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <p className="mt-2 whitespace-pre-wrap">
                                                    {
                                                        recommendation.recommendation_text
                                                    }
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {recommendation.author ??
                                                        'Unknown'}
                                                </p>
                                                {recommendation.approvals.map(
                                                    (approval) => (
                                                        <p
                                                            key={approval.id}
                                                            className="mt-2 text-xs text-muted-foreground"
                                                        >
                                                            {
                                                                approval.outcome_label
                                                            }{' '}
                                                            by{' '}
                                                            {approval.approver ??
                                                                'Unknown'}{' '}
                                                            ·{' '}
                                                            {new Date(
                                                                approval.decided_at,
                                                            ).toLocaleString()}
                                                            {approval.notes
                                                                ? ` — ${approval.notes}`
                                                                : ''}
                                                        </p>
                                                    ),
                                                )}
                                                {permissions.canApproveClinical &&
                                                recommendation.status ===
                                                    'awaiting_approval' &&
                                                !recommendation.is_ai_generated ? (
                                                    <form
                                                        className="mt-3 grid gap-3 md:grid-cols-[1fr_1fr_auto]"
                                                        onSubmit={(event) => {
                                                            event.preventDefault();
                                                            approvalForm.post(
                                                                `/${team.slug}/cases/${patientCase.reference}/clinical/recommendations/${recommendation.uuid}/decision`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Decision
                                                            </Label>
                                                            <Select
                                                                value={
                                                                    approvalForm
                                                                        .data
                                                                        .outcome ||
                                                                    undefined
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    approvalForm.setData(
                                                                        'outcome',
                                                                        value,
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger className="w-full">
                                                                    <SelectValue placeholder="Select decision" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {clinical.approval_outcome_options.map(
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
                                                                Notes
                                                            </Label>
                                                            <Input
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
                                                        <div className="flex items-end">
                                                            <Button
                                                                type="submit"
                                                                disabled={
                                                                    approvalForm.processing
                                                                }
                                                            >
                                                                Record decision
                                                            </Button>
                                                        </div>
                                                    </form>
                                                ) : null}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        ) : null}

                        {permissions.canReviewClinical ||
                        permissions.canApproveClinical ? (
                            <div className="grid gap-4 border-t pt-4 lg:grid-cols-2">
                                {permissions.canReviewClinical ? (
                                    <form
                                        className="space-y-3 rounded-lg border border-dashed p-3"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            observationForm.post(
                                                clinical.urls.store_observation,
                                                { preserveScroll: true },
                                            );
                                        }}
                                    >
                                        <h3 className="text-sm font-medium">
                                            Add clinical observation
                                        </h3>
                                        <Textarea
                                            className="min-h-24"
                                            value={observationForm.data.body}
                                            onChange={(event) =>
                                                observationForm.setData(
                                                    'body',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <Select
                                            value={
                                                observationForm.data.visibility
                                            }
                                            onValueChange={(value) =>
                                                observationForm.setData(
                                                    'visibility',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Visibility" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="internal">
                                                    Internal clinical only
                                                </SelectItem>
                                                <SelectItem value="coordinator_shared">
                                                    Shared with coordinator
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                observationForm.errors.body
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                            disabled={
                                                observationForm.processing
                                            }
                                        >
                                            Save observation
                                        </Button>
                                    </form>
                                ) : null}

                                {permissions.canApproveClinical ? (
                                    <>
                                        <form
                                            className="space-y-3 rounded-lg border border-dashed p-3"
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                recommendationForm.transform(
                                                    (data) => ({
                                                        ...data,
                                                        submit_for_approval:
                                                            false,
                                                    }),
                                                );
                                                recommendationForm.post(
                                                    clinical.urls
                                                        .store_recommendation,
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                );
                                            }}
                                        >
                                            <h3 className="text-sm font-medium">
                                                Draft clinical recommendation
                                            </h3>
                                            <Textarea
                                                className="min-h-28"
                                                value={
                                                    recommendationForm.data
                                                        .recommendation_text
                                                }
                                                onChange={(event) =>
                                                    recommendationForm.setData(
                                                        'recommendation_text',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    recommendationForm.errors
                                                        .recommendation_text
                                                }
                                            />
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    type="submit"
                                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                                    disabled={
                                                        recommendationForm.processing
                                                    }
                                                >
                                                    Save draft
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    disabled={
                                                        recommendationForm.processing
                                                    }
                                                    onClick={() => {
                                                        recommendationForm.transform(
                                                            (data) => ({
                                                                ...data,
                                                                submit_for_approval:
                                                                    true,
                                                            }),
                                                        );
                                                        recommendationForm.post(
                                                            clinical.urls
                                                                .store_recommendation,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Submit for approval
                                                </Button>
                                            </div>
                                        </form>

                                        <form
                                            className="space-y-3 rounded-lg border border-dashed p-3 lg:col-span-2"
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                reviewForm.post(
                                                    clinical.urls.store_review,
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                );
                                            }}
                                        >
                                            <h3 className="text-sm font-medium">
                                                Submit doctor review
                                            </h3>
                                            <div className="grid gap-3 lg:grid-cols-2">
                                                <Select
                                                    value={
                                                        reviewForm.data
                                                            .outcome ||
                                                        undefined
                                                    }
                                                    onValueChange={(value) =>
                                                        reviewForm.setData(
                                                            'outcome',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Outcome" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {clinical.outcome_options.map(
                                                            (option) => (
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
                                                <Textarea
                                                    className="min-h-28 lg:col-span-2"
                                                    placeholder="Human evaluation findings"
                                                    value={
                                                        reviewForm.data.findings
                                                    }
                                                    onChange={(event) =>
                                                        reviewForm.setData(
                                                            'findings',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                {reviewForm.data.outcome ===
                                                'need_more_information' ? (
                                                    <Textarea
                                                        className="min-h-24 lg:col-span-2"
                                                        placeholder="What additional information is required?"
                                                        value={
                                                            reviewForm.data
                                                                .additional_information_request
                                                        }
                                                        onChange={(event) =>
                                                            reviewForm.setData(
                                                                'additional_information_request',
                                                                event.target.value,
                                                            )
                                                        }
                                                    />
                                                ) : null}
                                            </div>
                                            <InputError
                                                message={
                                                    reviewForm.errors.findings
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
                                                disabled={reviewForm.processing}
                                            >
                                                Submit review
                                            </Button>
                                        </form>
                                    </>
                                ) : null}
                            </div>
                        ) : null}
                    </Card>
                ) : null}
                </>)}

                {tab === 'proposal' && (<>
                {proposals ? (
                    <Card title="Treatment proposals">
                        <p className="text-sm text-amber-800">
                            {proposals.disclaimer}
                        </p>

                        {proposals.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No treatment proposals yet.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {proposals.items.map((proposal) => (
                                    <li
                                        key={proposal.uuid}
                                        className="rounded-lg border p-3 text-sm"
                                    >
                                        <div className="flex flex-wrap gap-2">
                                            <Badge>
                                                {proposal.status_label}
                                            </Badge>
                                            <Badge variant="outline">
                                                {proposal.reference}
                                            </Badge>
                                            <Badge variant="secondary">
                                                v{proposal.version}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 font-medium">
                                            {proposal.proposed_procedure}
                                        </p>
                                        <p className="mt-1 text-muted-foreground">
                                            {proposal.estimated_cost
                                                ? `${proposal.estimated_cost} ${proposal.currency}`
                                                : 'Cost TBD'}
                                            {proposal.expected_duration
                                                ? ` · ${proposal.expected_duration}`
                                                : ''}
                                            {proposal.responsible_physician
                                                ? ` · ${proposal.responsible_physician.name}`
                                                : ''}
                                        </p>
                                        {proposal.line_items.length > 0 ? (
                                            <ul className="mt-2 space-y-1 text-xs text-muted-foreground">
                                                {proposal.line_items.map(
                                                    (item) => (
                                                        <li key={item.id}>
                                                            {item.quantity}×{' '}
                                                            {item.description}
                                                            {item.unit_cost
                                                                ? ` (${item.unit_cost} ${item.currency})`
                                                                : ''}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        ) : null}
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {permissions.canManageProposals &&
                                            proposal.status === 'draft' ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={
                                                        proposalTransitionForm.processing
                                                    }
                                                    onClick={() => {
                                                        proposalTransitionForm.transform(
                                                            () => ({
                                                                action: 'submit_for_approval',
                                                                notes: '',
                                                                patient_response:
                                                                    'accepted',
                                                            }),
                                                        );
                                                        proposalTransitionForm.post(
                                                            proposal.transition_url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Submit for clinical approval
                                                </Button>
                                            ) : null}
                                            {permissions.canApproveProposals &&
                                            proposal.status ===
                                                'awaiting_clinical_approval' ? (
                                                <>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={
                                                            proposalTransitionForm.processing
                                                        }
                                                        onClick={() => {
                                                            proposalTransitionForm.transform(
                                                                () => ({
                                                                    action: 'approve',
                                                                    notes: 'Clinically approved for administrative packaging.',
                                                                    patient_response:
                                                                        'accepted',
                                                                }),
                                                            );
                                                            proposalTransitionForm.post(
                                                                proposal.transition_url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        Approve proposal
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={
                                                            proposalTransitionForm.processing
                                                        }
                                                        onClick={() => {
                                                            proposalTransitionForm.transform(
                                                                () => ({
                                                                    action: 'return_to_draft',
                                                                    notes: 'Returned for revision.',
                                                                    patient_response:
                                                                        'accepted',
                                                                }),
                                                            );
                                                            proposalTransitionForm.post(
                                                                proposal.transition_url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        Return to draft
                                                    </Button>
                                                </>
                                            ) : null}
                                            {permissions.canManageProposals &&
                                            proposal.status === 'approved' ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={
                                                        proposalTransitionForm.processing
                                                    }
                                                    onClick={() => {
                                                        proposalTransitionForm.transform(
                                                            () => ({
                                                                action: 'send',
                                                                notes: '',
                                                                patient_response:
                                                                    'accepted',
                                                            }),
                                                        );
                                                        proposalTransitionForm.post(
                                                            proposal.transition_url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Send to patient
                                                </Button>
                                            ) : null}
                                            {permissions.canManageProposals &&
                                            proposal.status ===
                                                'sent_to_patient' ? (
                                                <>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={
                                                            proposalTransitionForm.processing
                                                        }
                                                        onClick={() => {
                                                            proposalTransitionForm.transform(
                                                                () => ({
                                                                    action: 'record_patient_response',
                                                                    notes: '',
                                                                    patient_response:
                                                                        'accepted',
                                                                }),
                                                            );
                                                            proposalTransitionForm.post(
                                                                proposal.transition_url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        Mark accepted
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={
                                                            proposalTransitionForm.processing
                                                        }
                                                        onClick={() => {
                                                            proposalTransitionForm.transform(
                                                                () => ({
                                                                    action: 'record_patient_response',
                                                                    notes: '',
                                                                    patient_response:
                                                                        'declined',
                                                                }),
                                                            );
                                                            proposalTransitionForm.post(
                                                                proposal.transition_url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        Mark declined
                                                    </Button>
                                                </>
                                            ) : null}
                                        </div>
                                        <InputError
                                            message={
                                                proposalTransitionForm.errors
                                                    .proposal
                                            }
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}

                        {permissions.canManageProposals ? (
                            <form
                                className="space-y-3 border-t pt-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    proposalForm.transform((data) => ({
                                        department_id: data.department_id
                                            ? Number(data.department_id)
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
                                        estimated_cost: data.estimated_cost
                                            ? Number(data.estimated_cost)
                                            : null,
                                        currency: data.currency,
                                        expected_duration:
                                            data.expected_duration || null,
                                        required_appointments:
                                            data.required_appointments
                                                ? Number(
                                                      data.required_appointments,
                                                  )
                                                : null,
                                        additional_information:
                                            data.additional_information ||
                                            null,
                                        valid_until: data.valid_until || null,
                                        items: data.items
                                            .filter(
                                                (item) =>
                                                    item.description.trim() !==
                                                    '',
                                            )
                                            .map((item) => ({
                                                description: item.description,
                                                quantity: Number(
                                                    item.quantity || 1,
                                                ),
                                                unit_cost: item.unit_cost
                                                    ? Number(item.unit_cost)
                                                    : null,
                                                currency: data.currency,
                                            })),
                                    }));
                                    proposalForm.post(proposals.urls.store, {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <h3 className="text-sm font-medium">
                                    Create proposal draft
                                </h3>
                                <div className="grid gap-3 lg:grid-cols-2">
                                    <div className="grid gap-2 lg:col-span-2">
                                        <Label>Proposed procedure</Label>
                                        <Input
                                            value={
                                                proposalForm.data
                                                    .proposed_procedure
                                            }
                                            onChange={(event) =>
                                                proposalForm.setData(
                                                    'proposed_procedure',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Estimated cost</Label>
                                        <Input
                                            value={
                                                proposalForm.data.estimated_cost
                                            }
                                            onChange={(event) =>
                                                proposalForm.setData(
                                                    'estimated_cost',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Currency</Label>
                                        <Input
                                            value={proposalForm.data.currency}
                                            onChange={(event) =>
                                                proposalForm.setData(
                                                    'currency',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Expected duration</Label>
                                        <Input
                                            value={
                                                proposalForm.data
                                                    .expected_duration
                                            }
                                            onChange={(event) =>
                                                proposalForm.setData(
                                                    'expected_duration',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Required appointments</Label>
                                        <Input
                                            value={
                                                proposalForm.data
                                                    .required_appointments
                                            }
                                            onChange={(event) =>
                                                proposalForm.setData(
                                                    'required_appointments',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2 lg:col-span-2">
                                        <Label>
                                            Link approved clinical recommendation
                                        </Label>
                                        <Select
                                            value={
                                                proposalForm.data
                                                    .clinical_recommendation_id ||
                                                UNASSIGNED
                                            }
                                            onValueChange={(value) =>
                                                proposalForm.setData(
                                                    'clinical_recommendation_id',
                                                    value === UNASSIGNED
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="None / administrative only" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={UNASSIGNED}>
                                                    None / administrative only
                                                </SelectItem>
                                                {proposals.approved_recommendations.map(
                                                    (recommendation) => (
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
                                                proposalForm.data.items[0]
                                                    ?.description ?? ''
                                            }
                                            onChange={(event) =>
                                                proposalForm.setData('items', [
                                                    {
                                                        ...proposalForm.data
                                                            .items[0],
                                                        description:
                                                            event.target.value,
                                                    },
                                                ])
                                            }
                                        />
                                    </div>
                                </div>
                                <InputError
                                    message={
                                        proposalForm.errors.proposed_procedure
                                    }
                                />
                                <Button
                                    type="submit"
                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                    disabled={proposalForm.processing}
                                >
                                    Save proposal draft
                                </Button>
                            </form>
                        ) : null}
                    </Card>
                ) : null}
                </>)}

                {tab === 'appointments' && (<>
                {appointments ? (
                    <Card title="Appointments">
                        <p className="text-sm text-amber-800">
                            {appointments.disclaimer}
                        </p>

                        {appointments.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No appointments planned yet.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {appointments.items.map((appointment) => (
                                    <li
                                        key={appointment.uuid}
                                        className="rounded-lg border p-3 text-sm"
                                    >
                                        <div className="flex flex-wrap gap-2">
                                            <Badge>
                                                {appointment.status_label}
                                            </Badge>
                                            <Badge variant="outline">
                                                {
                                                    appointment.appointment_type_label
                                                }
                                            </Badge>
                                            {appointment.hospital_slot_verified ? (
                                                <Badge variant="secondary">
                                                    Hospital verified
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <p className="mt-2 font-medium">
                                            {appointment.confirmed_starts_at
                                                ? `Confirmed ${new Date(appointment.confirmed_starts_at).toLocaleString()}`
                                                : appointment.proposed_starts_at
                                                  ? `Proposed ${new Date(appointment.proposed_starts_at).toLocaleString()}`
                                                  : 'Timing TBD'}
                                        </p>
                                        <p className="mt-1 text-muted-foreground">
                                            {appointment.location ||
                                                'Location TBD'}
                                            {appointment.doctor
                                                ? ` · ${appointment.doctor.name}`
                                                : ''}
                                            {appointment.department
                                                ? ` · ${appointment.department}`
                                                : ''}
                                        </p>
                                        {appointment.confirmation_source_label ? (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Source:{' '}
                                                {
                                                    appointment.confirmation_source_label
                                                }
                                                {appointment.external_his_reference
                                                    ? ` · HIS ${appointment.external_his_reference}`
                                                    : ''}
                                            </p>
                                        ) : null}
                                        {permissions.canManageAppointments ? (
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {appointment.status ===
                                                'proposed' ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={
                                                            appointmentTransitionForm.processing
                                                        }
                                                        onClick={() => {
                                                            appointmentTransitionForm.transform(
                                                                () => ({
                                                                    action: 'request_confirmation',
                                                                    confirmation_source:
                                                                        'manual_hospital_verified',
                                                                    hospital_slot_verified: false,
                                                                    confirmed_starts_at:
                                                                        '',
                                                                    confirmed_ends_at:
                                                                        '',
                                                                    external_his_reference:
                                                                        appointment.external_his_reference ??
                                                                        '',
                                                                    proposed_starts_at:
                                                                        '',
                                                                    proposed_ends_at:
                                                                        '',
                                                                    notes: '',
                                                                }),
                                                            );
                                                            appointmentTransitionForm.post(
                                                                appointment.transition_url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        Request confirmation
                                                    </Button>
                                                ) : null}
                                                {(appointment.status ===
                                                    'awaiting_confirmation' ||
                                                    appointment.status ===
                                                        'rescheduled') && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={
                                                            appointmentTransitionForm.processing
                                                        }
                                                        onClick={() => {
                                                            appointmentTransitionForm.transform(
                                                                () => ({
                                                                    action: 'confirm',
                                                                    confirmation_source:
                                                                        'manual_hospital_verified',
                                                                    hospital_slot_verified: true,
                                                                    confirmed_starts_at:
                                                                        appointment.proposed_starts_at ??
                                                                        '',
                                                                    confirmed_ends_at:
                                                                        appointment.proposed_ends_at ??
                                                                        '',
                                                                    external_his_reference:
                                                                        appointment.external_his_reference ??
                                                                        '',
                                                                    proposed_starts_at:
                                                                        '',
                                                                    proposed_ends_at:
                                                                        '',
                                                                    notes: 'Confirmed with hospital scheduling.',
                                                                }),
                                                            );
                                                            appointmentTransitionForm.post(
                                                                appointment.transition_url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        Confirm (hospital
                                                        verified)
                                                    </Button>
                                                )}
                                                {appointment.status !==
                                                    'cancelled' &&
                                                appointment.status !==
                                                    'completed' ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={
                                                            appointmentTransitionForm.processing
                                                        }
                                                        onClick={() => {
                                                            appointmentTransitionForm.transform(
                                                                () => ({
                                                                    action: 'cancel',
                                                                    confirmation_source:
                                                                        'manual_hospital_verified',
                                                                    hospital_slot_verified: false,
                                                                    confirmed_starts_at:
                                                                        '',
                                                                    confirmed_ends_at:
                                                                        '',
                                                                    external_his_reference:
                                                                        '',
                                                                    proposed_starts_at:
                                                                        '',
                                                                    proposed_ends_at:
                                                                        '',
                                                                    notes: 'Cancelled by coordinator.',
                                                                }),
                                                            );
                                                            appointmentTransitionForm.post(
                                                                appointment.transition_url,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        Cancel
                                                    </Button>
                                                ) : null}
                                            </div>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {permissions.canManageAppointments ? (
                            <form
                                className="space-y-3 rounded-lg border border-dashed p-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    appointmentForm.transform((data) => ({
                                        department_id: data.department_id
                                            ? Number(data.department_id)
                                            : null,
                                        doctor_id:
                                            data.doctor_id &&
                                            data.doctor_id !== UNASSIGNED
                                                ? Number(data.doctor_id)
                                                : null,
                                        appointment_type: data.appointment_type,
                                        proposed_starts_at:
                                            data.proposed_starts_at,
                                        proposed_ends_at:
                                            data.proposed_ends_at || null,
                                        location: data.location || null,
                                        meeting_instructions:
                                            data.meeting_instructions || null,
                                        notes: data.notes || null,
                                        external_his_reference:
                                            data.external_his_reference || null,
                                    }));
                                    appointmentForm.post(
                                        appointments.urls.store,
                                        {
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                <h3 className="text-sm font-medium">
                                    Propose appointment
                                </h3>
                                <div className="grid gap-3 lg:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Type</Label>
                                        <Select
                                            value={
                                                appointmentForm.data
                                                    .appointment_type ||
                                                undefined
                                            }
                                            onValueChange={(value) =>
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
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Proposed start</Label>
                                        <Input
                                            type="datetime-local"
                                            value={
                                                appointmentForm.data
                                                    .proposed_starts_at
                                            }
                                            onChange={(event) =>
                                                appointmentForm.setData(
                                                    'proposed_starts_at',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Location</Label>
                                        <Input
                                            value={
                                                appointmentForm.data.location
                                            }
                                            onChange={(event) =>
                                                appointmentForm.setData(
                                                    'location',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Doctor</Label>
                                        <Select
                                            value={
                                                appointmentForm.data.doctor_id ||
                                                UNASSIGNED
                                            }
                                            onValueChange={(value) =>
                                                appointmentForm.setData(
                                                    'doctor_id',
                                                    value === UNASSIGNED
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Unassigned" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={UNASSIGNED}>
                                                    Unassigned
                                                </SelectItem>
                                                {assignableMembers.doctors.map(
                                                    (doctor) => (
                                                        <SelectItem
                                                            key={doctor.id}
                                                            value={String(
                                                                doctor.id,
                                                            )}
                                                        >
                                                            {doctor.name}
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
                                        appointmentForm.errors.appointment
                                    }
                                />
                                <Button
                                    type="submit"
                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                    disabled={appointmentForm.processing}
                                >
                                    Save proposed appointment
                                </Button>
                            </form>
                        ) : null}
                    </Card>
                ) : null}
                </>)}

                {tab === 'travel' && (<>
                {travel ? (
                    <Card title="Travel coordination">
                        <p className="text-sm text-amber-800">
                            {travel.disclaimer}
                        </p>
                        {travel.arrangement ? (
                            <p className="text-sm text-muted-foreground">
                                Status: {travel.arrangement.status_label}
                                {travel.arrangement.expected_arrival_date
                                    ? ` · Arrive ${travel.arrangement.expected_arrival_date}`
                                    : ''}
                                {travel.arrangement.visa_assistance_status_label
                                    ? ` · Visa: ${travel.arrangement.visa_assistance_status_label}`
                                    : ''}
                            </p>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No travel arrangement yet.
                            </p>
                        )}
                        {permissions.canManageTravel ? (
                            <form
                                className="space-y-3 rounded-lg border border-dashed p-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    travelForm.transform((data) => ({
                                        ...data,
                                        assigned_coordinator_id:
                                            data.assigned_coordinator_id
                                                ? Number(
                                                      data.assigned_coordinator_id,
                                                  )
                                                : null,
                                        expected_arrival_date:
                                            data.expected_arrival_date || null,
                                        expected_departure_date:
                                            data.expected_departure_date ||
                                            null,
                                        flight_information:
                                            data.flight_information || null,
                                        airport_pickup_details:
                                            data.airport_pickup_details || null,
                                        transfer_arrangements:
                                            data.transfer_arrangements || null,
                                        accommodation:
                                            data.accommodation || null,
                                        hotel_details:
                                            data.hotel_details || null,
                                        special_administrative_requirements:
                                            data.special_administrative_requirements ||
                                            null,
                                        notes: data.notes || null,
                                    }));
                                    travelForm.put(travel.urls.upsert, {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <div className="grid gap-3 lg:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Status</Label>
                                        <Select
                                            value={travelForm.data.status}
                                            onValueChange={(value) =>
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
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Visa assistance</Label>
                                        <Select
                                            value={
                                                travelForm.data
                                                    .visa_assistance_status
                                            }
                                            onValueChange={(value) =>
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
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Expected arrival</Label>
                                        <Input
                                            type="date"
                                            value={
                                                travelForm.data
                                                    .expected_arrival_date
                                            }
                                            onChange={(event) =>
                                                travelForm.setData(
                                                    'expected_arrival_date',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Expected departure</Label>
                                        <Input
                                            type="date"
                                            value={
                                                travelForm.data
                                                    .expected_departure_date
                                            }
                                            onChange={(event) =>
                                                travelForm.setData(
                                                    'expected_departure_date',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2 lg:col-span-2">
                                        <Label>
                                            Flight information (no passport
                                            data)
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
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-3 lg:col-span-2">
                                        <ToggleField
                                            id="travel-airport-pickup"
                                            label="Airport pickup needed"
                                            description="Arrange a transfer from the airport when the patient lands."
                                            checked={travelForm.data.airport_pickup_required}
                                            onChange={(value) =>
                                                travelForm.setData(
                                                    'airport_pickup_required',
                                                    value,
                                                )
                                            }
                                        />
                                        {travelForm.data.airport_pickup_required ? (
                                            <div className="grid gap-2">
                                                <Label htmlFor="travel-pickup-details">
                                                    Pickup details
                                                </Label>
                                                <Input
                                                    id="travel-pickup-details"
                                                    placeholder="Driver, meeting point, flight number to watch…"
                                                    value={travelForm.data.airport_pickup_details}
                                                    onChange={(event) =>
                                                        travelForm.setData(
                                                            'airport_pickup_details',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        ) : null}
                                    </div>
                                    <div className="grid gap-2 lg:col-span-2">
                                        <Label>Hotel / accommodation</Label>
                                        <Input
                                            value={
                                                travelForm.data.hotel_details
                                            }
                                            onChange={(event) =>
                                                travelForm.setData(
                                                    'hotel_details',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <InputError
                                    message={travelForm.errors.travel}
                                />
                                <Button
                                    type="submit"
                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                    disabled={travelForm.processing}
                                >
                                    Save travel arrangement
                                </Button>
                            </form>
                        ) : null}
                    </Card>
                ) : null}
                </>)}

                {tab === 'tasks' && (<>
                {tasks || followUps ? (
                    <section className="grid gap-4 lg:grid-cols-2">
                {tasks ? (
                    <Card title="Tasks">
                        {tasks.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No tasks yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {tasks.items.map((task) => (
                                    <li
                                        key={task.uuid}
                                        className="rounded-lg border p-3 text-sm"
                                    >
                                        <div className="flex flex-wrap gap-2">
                                            <Badge>{task.status_label}</Badge>
                                            <Badge variant="outline">
                                                {task.priority_label}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 font-medium">
                                            {task.title}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {task.assigned_user
                                                ? task.assigned_user.name
                                                : 'Unassigned'}
                                            {task.due_at
                                                ? ` · Due ${new Date(task.due_at).toLocaleString()}`
                                                : ''}
                                        </p>
                                        {permissions.canManageTasks &&
                                        task.status !== 'completed' &&
                                        task.status !== 'cancelled' ? (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={
                                                        taskTransitionForm.processing
                                                    }
                                                    onClick={() => {
                                                        taskTransitionForm.transform(
                                                            () => ({
                                                                status: 'in_progress',
                                                                comment: '',
                                                            }),
                                                        );
                                                        taskTransitionForm.post(
                                                            task.transition_url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Start
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={
                                                        taskTransitionForm.processing
                                                    }
                                                    onClick={() => {
                                                        taskTransitionForm.transform(
                                                            () => ({
                                                                status: 'completed',
                                                                comment: '',
                                                            }),
                                                        );
                                                        taskTransitionForm.post(
                                                            task.transition_url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Complete
                                                </Button>
                                            </div>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {permissions.canManageTasks ? (
                            <form
                                className="space-y-3 rounded-lg border border-dashed p-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    taskForm.transform((data) => ({
                                        title: data.title,
                                        description: data.description || null,
                                        assigned_user_id:
                                            data.assigned_user_id &&
                                            data.assigned_user_id !==
                                                UNASSIGNED
                                                ? Number(data.assigned_user_id)
                                                : null,
                                        priority: data.priority,
                                        due_at: data.due_at || null,
                                    }));
                                    taskForm.post(tasks.urls.store, {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <h3 className="text-sm font-medium">
                                    Create task
                                </h3>
                                <div className="grid gap-3 lg:grid-cols-2">
                                    <div className="grid gap-2 lg:col-span-2">
                                        <Label>Title</Label>
                                        <Input
                                            value={taskForm.data.title}
                                            onChange={(event) =>
                                                taskForm.setData(
                                                    'title',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Priority</Label>
                                        <Select
                                            value={taskForm.data.priority}
                                            onValueChange={(value) =>
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
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Assignee</Label>
                                        <Select
                                            value={
                                                taskForm.data.assigned_user_id ||
                                                UNASSIGNED
                                            }
                                            onValueChange={(value) =>
                                                taskForm.setData(
                                                    'assigned_user_id',
                                                    value === UNASSIGNED
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Unassigned" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={UNASSIGNED}>
                                                    Unassigned
                                                </SelectItem>
                                                {assignableMembers.staff.map(
                                                    (member) => (
                                                        <SelectItem
                                                            key={member.id}
                                                            value={String(
                                                                member.id,
                                                            )}
                                                        >
                                                            {member.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <InputError
                                    message={
                                        taskForm.errors.title ||
                                        taskForm.errors.task
                                    }
                                />
                                <Button
                                    type="submit"
                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                    disabled={taskForm.processing}
                                >
                                    Save task
                                </Button>
                            </form>
                        ) : null}
                    </Card>
                ) : null}

                {followUps ? (
                    <Card title="Follow-ups">
                        {followUps.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No follow-ups scheduled yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {followUps.items.map((followUp) => (
                                    <li
                                        key={followUp.uuid}
                                        className="rounded-lg border p-3 text-sm"
                                    >
                                        <div className="flex flex-wrap gap-2">
                                            <Badge>
                                                {followUp.status_label}
                                            </Badge>
                                            <Badge variant="outline">
                                                {
                                                    followUp.follow_up_type_label
                                                }
                                            </Badge>
                                            {followUp.is_overdue ? (
                                                <Badge variant="destructive">
                                                    Overdue
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <p className="mt-2 font-medium">
                                            {followUp.title}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {followUp.assigned_user
                                                ? followUp.assigned_user.name
                                                : 'Unassigned'}
                                            {followUp.due_at
                                                ? ` · Due ${new Date(followUp.due_at).toLocaleString()}`
                                                : ''}
                                        </p>
                                        {followUp.outcome_notes ? (
                                            <p className="mt-1 text-muted-foreground">
                                                Outcome: {followUp.outcome_notes}
                                            </p>
                                        ) : null}
                                        {permissions.canManageTasks &&
                                        followUp.status !== 'completed' &&
                                        followUp.status !== 'cancelled' ? (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={
                                                        followUpCompleteForm.processing
                                                    }
                                                    onClick={() => {
                                                        followUpCompleteForm.transform(
                                                            () => ({
                                                                outcome_notes:
                                                                    'Completed from case workspace',
                                                                cancel: false,
                                                            }),
                                                        );
                                                        followUpCompleteForm.post(
                                                            followUp.complete_url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Complete
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        followUpCompleteForm.processing
                                                    }
                                                    onClick={() => {
                                                        followUpCompleteForm.transform(
                                                            () => ({
                                                                outcome_notes:
                                                                    'Cancelled from case workspace',
                                                                cancel: true,
                                                            }),
                                                        );
                                                        followUpCompleteForm.post(
                                                            followUp.complete_url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {permissions.canManageTasks ? (
                            <form
                                className="space-y-3 rounded-lg border border-dashed p-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    followUpForm.transform((data) => ({
                                        title: data.title,
                                        description: data.description || null,
                                        follow_up_type: data.follow_up_type,
                                        due_at: data.due_at,
                                        assigned_user_id:
                                            data.assigned_user_id &&
                                            data.assigned_user_id !==
                                                UNASSIGNED
                                                ? Number(data.assigned_user_id)
                                                : null,
                                    }));
                                    followUpForm.post(followUps.urls.store, {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <h3 className="text-sm font-medium">
                                    Schedule follow-up
                                </h3>
                                <div className="grid gap-3 lg:grid-cols-2">
                                    <div className="grid gap-2 lg:col-span-2">
                                        <Label>Title</Label>
                                        <Input
                                            value={followUpForm.data.title}
                                            onChange={(event) =>
                                                followUpForm.setData(
                                                    'title',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Type</Label>
                                        <Select
                                            value={
                                                followUpForm.data.follow_up_type
                                            }
                                            onValueChange={(value) =>
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
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Due</Label>
                                        <Input
                                            type="datetime-local"
                                            value={followUpForm.data.due_at}
                                            onChange={(event) =>
                                                followUpForm.setData(
                                                    'due_at',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2 lg:col-span-2">
                                        <Label>Assignee</Label>
                                        <Select
                                            value={
                                                followUpForm.data
                                                    .assigned_user_id ||
                                                UNASSIGNED
                                            }
                                            onValueChange={(value) =>
                                                followUpForm.setData(
                                                    'assigned_user_id',
                                                    value === UNASSIGNED
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Case coordinator (default)" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={UNASSIGNED}>
                                                    Case coordinator (default)
                                                </SelectItem>
                                                {assignableMembers.staff.map(
                                                    (member) => (
                                                        <SelectItem
                                                            key={member.id}
                                                            value={String(
                                                                member.id,
                                                            )}
                                                        >
                                                            {member.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <InputError
                                    message={
                                        followUpForm.errors.title ||
                                        followUpForm.errors.due_at ||
                                        followUpForm.errors.follow_up
                                    }
                                />
                                <Button
                                    type="submit"
                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                    disabled={followUpForm.processing}
                                >
                                    Save follow-up
                                </Button>
                            </form>
                        ) : null}
                    </Card>
                ) : null}
                    </section>
                ) : null}
                </>)}

                {tab === 'communications' && (<>
                {communications ? (
                    <Card title="Communications">
                        <p className="text-sm text-amber-800">
                            {communications.disclaimer}
                        </p>
                        {communications.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No communications yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {communications.items.map((item) => (
                                    <li
                                        key={item.uuid}
                                        className="rounded-lg border p-3 text-sm"
                                    >
                                        <div className="flex flex-wrap gap-2">
                                            <Badge>
                                                {item.channel_label}
                                            </Badge>
                                            <Badge variant="outline">
                                                {item.status_label}
                                            </Badge>
                                            {item.is_ai_draft ? (
                                                <Badge variant="secondary">
                                                    AI draft
                                                </Badge>
                                            ) : null}
                                        </div>
                                        {item.subject ? (
                                            <p className="mt-2 font-medium">
                                                {item.subject}
                                            </p>
                                        ) : null}
                                        <p className="mt-1 whitespace-pre-wrap text-muted-foreground">
                                            {item.body}
                                        </p>
                                        {permissions.canManageCommunications &&
                                        item.requires_approval ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                className="mt-2"
                                                disabled={
                                                    communicationApproveForm.processing
                                                }
                                                onClick={() => {
                                                    communicationApproveForm.transform(
                                                        () => ({
                                                            mark_sent: false,
                                                        }),
                                                    );
                                                    communicationApproveForm.post(
                                                        item.approve_url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }}
                                            >
                                                Approve (do not auto-send)
                                            </Button>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {permissions.canManageCommunications ? (
                            <form
                                className="space-y-3 rounded-lg border border-dashed p-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    communicationForm.transform((data) => ({
                                        channel: data.channel,
                                        body: data.body || null,
                                        subject: data.subject || null,
                                        recipient: data.recipient || null,
                                        log_only: data.log_only,
                                        draft_with_ai: data.draft_with_ai,
                                        ai_brief: data.ai_brief || null,
                                    }));
                                    communicationForm.post(
                                        communications.urls.store,
                                        {
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                <h3 className="text-sm font-medium">
                                    Log communication / draft
                                </h3>
                                <div className="grid gap-3 lg:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Channel</Label>
                                        <Select
                                            value={
                                                communicationForm.data.channel
                                            }
                                            onValueChange={(value) =>
                                                communicationForm.setData(
                                                    'channel',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Channel" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {communications.channel_options.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <ToggleField
                                        id="communication-log-only"
                                        className="self-end"
                                        label="Log only"
                                        description="Keep a record here without sending anything to the patient."
                                        checked={communicationForm.data.log_only}
                                        onChange={(value) =>
                                            communicationForm.setData(
                                                'log_only',
                                                value,
                                            )
                                        }
                                    />
                                    <div className="grid gap-2 lg:col-span-2">
                                        <Label>Body</Label>
                                        <Textarea
                                            className="min-h-24"
                                            value={communicationForm.data.body}
                                            onChange={(event) =>
                                                communicationForm.setData(
                                                    'body',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                {communicationForm.data.channel ===
                                'patient_portal' ? (
                                    <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                        {communicationForm.data.log_only
                                            ? 'Log-only messages are never shown to the patient.'
                                            : 'Once approved, this message appears on the patient’s private tracking page. Do not include clinical advice or diagnoses.'}
                                    </p>
                                ) : null}
                                <InputError
                                    message={
                                        communicationForm.errors.body ||
                                        communicationForm.errors.communication
                                    }
                                />
                                <Button
                                    type="submit"
                                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                                    disabled={communicationForm.processing}
                                >
                                    Save communication
                                </Button>
                            </form>
                        ) : null}
                    </Card>
                ) : null}
                </>)}

                {tab === 'timeline' && (<>
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
                            {patientCase.activities.map((activity) => (
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
                            ))}
                        </ol>
                    )}
                </Card>
                </>)}
                </div>
            </div>
        </>
    );
}

function Card({
    title,
    description,
    icon: Icon,
    children,
}: {
    title: string;
    description?: string;
    icon?: typeof UserRound;
    children: ReactNode;
}) {
    return (
        <section className="light-surface rounded-2xl border border-slate-200/80 bg-white shadow-[0_12px_40px_-28px_rgba(15,23,42,0.35)]">
            <div className="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
                {Icon ? (
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                        <Icon className="size-3.5" strokeWidth={1.75} />
                    </span>
                ) : null}
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold text-slate-900">
                        {title}
                    </h2>
                    {description ? (
                        <p className="text-xs text-slate-500">{description}</p>
                    ) : null}
                </div>
            </div>
            <div className="space-y-3 p-5">{children}</div>
        </section>
    );
}

function Field({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
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

function Item({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return <Detail label={label} value={value} />;
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
