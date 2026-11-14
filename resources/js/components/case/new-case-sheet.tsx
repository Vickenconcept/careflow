import { useForm } from '@inertiajs/react';
import { AppSelect } from '@/components/app-select';
import { FormSheet } from '@/components/case/form-sheet';
import InputError from '@/components/input-error';
import { ToggleField } from '@/components/toggle-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Option = { value: string; label: string };

export type NewCaseOptions = {
    url: string;
    sources: Option[];
    languages: Option[];
    departments: Option[];
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: NewCaseOptions;
};

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label className="text-sm font-medium text-slate-700">
                {label}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

/**
 * A side panel for entering an enquiry that came in by phone, email, WhatsApp,
 * a walk-in or an agent, so every patient ends up in CareFlow, not only portal ones.
 */
export function NewCaseSheet({ open, onOpenChange, options }: Props) {
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        country_of_residence: '',
        preferred_language: 'en',
        department_id: '',
        source: 'phone',
        reason_for_contact: '',
        diagnosis: '',
        additional_comments: '',
        consent_confirmed: false,
        ai_processing_consent: false,
        email_patient: true,
    });

    return (
        <FormSheet
            open={open}
            onOpenChange={onOpenChange}
            title="New case"
            description="For enquiries that came in by phone, email, WhatsApp, in person or through an agent. If the email belongs to an existing patient, the case is added to their record."
        >
            <form
                className="space-y-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        department_id: data.department_id || null,
                        country_of_residence:
                            data.country_of_residence.trim().toUpperCase() ||
                            null,
                        phone: data.phone || null,
                        diagnosis: data.diagnosis || null,
                        additional_comments: data.additional_comments || null,
                    }));
                    form.post(options.url, {
                        onSuccess: () => {
                            form.reset();
                            onOpenChange(false);
                        },
                    });
                }}
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="First name" error={form.errors.first_name}>
                        <Input
                            value={form.data.first_name}
                            onChange={(event) =>
                                form.setData('first_name', event.target.value)
                            }
                        />
                    </Field>
                    <Field label="Last name" error={form.errors.last_name}>
                        <Input
                            value={form.data.last_name}
                            onChange={(event) =>
                                form.setData('last_name', event.target.value)
                            }
                        />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <Input
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                    </Field>
                    <Field label="Phone (optional)" error={form.errors.phone}>
                        <Input
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Country code (optional)"
                        error={form.errors.country_of_residence}
                    >
                        <Input
                            maxLength={2}
                            placeholder="DE"
                            value={form.data.country_of_residence}
                            onChange={(event) =>
                                form.setData(
                                    'country_of_residence',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field
                        label="Preferred language"
                        error={form.errors.preferred_language}
                    >
                        <AppSelect
                            value={form.data.preferred_language}
                            onChange={(value) =>
                                form.setData('preferred_language', value)
                            }
                            options={options.languages}
                        />
                    </Field>
                    <Field
                        label="How did they contact us?"
                        error={form.errors.source}
                    >
                        <AppSelect
                            value={form.data.source}
                            onChange={(value) => form.setData('source', value)}
                            options={options.sources}
                        />
                    </Field>
                    <Field
                        label="Department (optional)"
                        error={form.errors.department_id}
                    >
                        <AppSelect
                            value={form.data.department_id}
                            onChange={(value) =>
                                form.setData('department_id', value)
                            }
                            options={options.departments}
                            emptyLabel="Not decided yet"
                        />
                    </Field>
                </div>

                <Field
                    label="What are they asking for?"
                    error={form.errors.reason_for_contact}
                >
                    <Textarea
                        className="min-h-24"
                        placeholder="Second opinion on a knee replacement, wants to travel in March"
                        value={form.data.reason_for_contact}
                        onChange={(event) =>
                            form.setData(
                                'reason_for_contact',
                                event.target.value,
                            )
                        }
                    />
                </Field>
                <Field
                    label="Known diagnosis (optional)"
                    error={form.errors.diagnosis}
                >
                    <Input
                        value={form.data.diagnosis}
                        onChange={(event) =>
                            form.setData('diagnosis', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Notes (optional)"
                    error={form.errors.additional_comments}
                >
                    <Textarea
                        className="min-h-20"
                        value={form.data.additional_comments}
                        onChange={(event) =>
                            form.setData(
                                'additional_comments',
                                event.target.value,
                            )
                        }
                    />
                </Field>

                <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <label className="flex items-start gap-3 text-sm">
                        <Checkbox
                            className="mt-0.5"
                            checked={form.data.consent_confirmed}
                            onCheckedChange={(value) =>
                                form.setData(
                                    'consent_confirmed',
                                    value === true,
                                )
                            }
                        />
                        <span>
                            <span className="font-medium text-slate-900">
                                The patient agreed
                            </span>{' '}
                            to the hospital handling their enquiry and health
                            information.
                            <span className="ms-1.5 rounded-full bg-white px-1.5 py-0.5 text-[11px] font-medium text-slate-600">
                                Required
                            </span>
                        </span>
                    </label>
                    <InputError message={form.errors.consent_confirmed} />
                    <label className="flex items-start gap-3 text-sm">
                        <Checkbox
                            className="mt-0.5"
                            checked={form.data.ai_processing_consent}
                            onCheckedChange={(value) =>
                                form.setData(
                                    'ai_processing_consent',
                                    value === true,
                                )
                            }
                        />
                        <span>
                            The patient also agreed to AI helping read and
                            translate their documents.
                            <span className="ms-1.5 rounded-full bg-white px-1.5 py-0.5 text-[11px] font-medium text-slate-600">
                                Optional
                            </span>
                        </span>
                    </label>
                </div>

                <ToggleField
                    id="new-case-email-patient"
                    label="Email the patient a link to their case page"
                    description="They can upload documents and message the team from there."
                    checked={form.data.email_patient}
                    onChange={(value) => form.setData('email_patient', value)}
                />

                <Button
                    type="submit"
                    className="h-11 rounded-full bg-brand text-white hover:bg-brand/90"
                    disabled={form.processing}
                >
                    {form.processing ? 'Creating…' : 'Create case'}
                </Button>
            </form>
        </FormSheet>
    );
}
