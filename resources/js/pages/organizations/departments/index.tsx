import { Form, Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Network, Plus } from 'lucide-react';
import { useState } from 'react';
import {
    DataTable,
    DataTableBody,
    DataTableCard,
    DataTableCell,
    DataTableHead,
    DataTableHeader,
    DataTableRow,
} from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { dashboard } from '@/routes';
import type { Department, Team } from '@/types';

type Props = {
    team: Pick<Team, 'id' | 'name' | 'slug'>;
    departments: Department[];
    permissions: {
        canManageDepartments: boolean;
    };
};

type DepartmentFormState = {
    name: string;
    description: string;
    is_active: boolean;
};

export default function DepartmentsIndex({
    team,
    departments,
    permissions,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editingDepartment, setEditingDepartment] =
        useState<Department | null>(null);

    const createForm = useForm<DepartmentFormState>({
        name: '',
        description: '',
        is_active: true,
    });

    const editForm = useForm<DepartmentFormState>({
        name: '',
        description: '',
        is_active: true,
    });

    const openCreateModal = () => {
        createForm.clearErrors();
        createForm.setData({
            name: '',
            description: '',
            is_active: true,
        });
        setCreateOpen(true);
    };

    const closeCreateModal = () => {
        setCreateOpen(false);
    };

    const openEditModal = (department: Department) => {
        editForm.clearErrors();
        editForm.setData({
            name: department.name,
            description: department.description ?? '',
            is_active: department.is_active,
        });
        setEditingDepartment(department);
    };

    const closeEditModal = () => {
        setEditingDepartment(null);
    };

    const moveDepartment = (
        department: Department,
        direction: 'up' | 'down',
    ) => {
        router.patch(
            `/${team.slug}/organization/departments/${department.slug}/reorder`,
            { direction },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Departments" />

            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Departments"
                        description="Clinical and service departments available for patient enquiries."
                    />
                    {permissions.canManageDepartments ? (
                        <Button
                            type="button"
                            className="rounded-xl bg-brand text-white hover:bg-brand/90"
                            onClick={openCreateModal}
                        >
                            <Plus className="size-4" />
                            Add department
                        </Button>
                    ) : null}
                </div>

                {departments.length === 0 ? (
                    <EmptyState
                        icon={Network}
                        title="No departments yet"
                        description="Create departments so patient intake can route enquiries to the right clinical teams."
                        action={
                            permissions.canManageDepartments ? (
                                <Button
                                    type="button"
                                    className="rounded-xl bg-brand text-white hover:bg-brand/90"
                                    onClick={openCreateModal}
                                >
                                    <Plus className="size-4" />
                                    Add department
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <DataTableCard>
                        <DataTable>
                            <DataTableHeader>
                                <DataTableHead>Name</DataTableHead>
                                <DataTableHead>Code</DataTableHead>
                                <DataTableHead>Status</DataTableHead>
                                {permissions.canManageDepartments ? (
                                    <DataTableHead className="text-right">
                                        Actions
                                    </DataTableHead>
                                ) : null}
                            </DataTableHeader>
                            <DataTableBody>
                                {departments.map((department, index) => (
                                    <DataTableRow key={department.id}>
                                        <DataTableCell>
                                            <div className="font-semibold text-slate-900">
                                                {department.name}
                                            </div>
                                            {department.description ? (
                                                <div className="mt-1 text-xs text-slate-500">
                                                    {department.description}
                                                </div>
                                            ) : null}
                                        </DataTableCell>
                                        <DataTableCell className="font-mono text-xs text-slate-500">
                                            {department.code ?? '—'}
                                        </DataTableCell>
                                        <DataTableCell>
                                            <Badge
                                                className={
                                                    department.is_active
                                                        ? 'rounded-full border-emerald-200 bg-emerald-50 text-emerald-700'
                                                        : 'rounded-full border-slate-200 bg-slate-50 text-slate-600'
                                                }
                                            >
                                                {department.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </DataTableCell>
                                        {permissions.canManageDepartments ? (
                                            <DataTableCell>
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-8 rounded-lg border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                                                        disabled={index === 0}
                                                        onClick={() =>
                                                            moveDepartment(
                                                                department,
                                                                'up',
                                                            )
                                                        }
                                                        aria-label={`Move ${department.name} up`}
                                                    >
                                                        <ArrowUp className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-8 rounded-lg border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                                                        disabled={
                                                            index ===
                                                            departments.length -
                                                                1
                                                        }
                                                        onClick={() =>
                                                            moveDepartment(
                                                                department,
                                                                'down',
                                                            )
                                                        }
                                                        aria-label={`Move ${department.name} down`}
                                                    >
                                                        <ArrowDown className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="rounded-full"
                                                        onClick={() =>
                                                            openEditModal(
                                                                department,
                                                            )
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Form
                                                        action={`/${team.slug}/organization/departments/${department.slug}`}
                                                        method="delete"
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                        onSubmit={(event) => {
                                                            if (
                                                                !confirm(
                                                                    `Delete ${department.name}?`,
                                                                )
                                                            ) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                variant="destructive"
                                                                size="sm"
                                                                className="rounded-full"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Delete
                                                            </Button>
                                                        )}
                                                    </Form>
                                                </div>
                                            </DataTableCell>
                                        ) : null}
                                    </DataTableRow>
                                ))}
                            </DataTableBody>
                        </DataTable>
                    </DataTableCard>
                )}
            </div>

            <Dialog
                open={createOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeCreateModal();
                    } else {
                        setCreateOpen(true);
                    }
                }}
            >
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-sm">
                    <DialogHeader className="space-y-1 px-5 pt-5 pb-3">
                        <DialogTitle>Add department</DialogTitle>
                        <DialogDescription>
                            Patients can select this during intake.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        className="space-y-3.5 px-5 pb-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createForm.post(
                                `/${team.slug}/organization/departments`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        closeCreateModal();
                                        createForm.reset();
                                    },
                                },
                            );
                        }}
                    >
                        <DepartmentFields
                            idPrefix="create"
                            form={createForm}
                        />
                        <Button
                            type="submit"
                            className="w-full rounded-xl bg-brand text-white hover:bg-brand/90"
                            disabled={createForm.processing}
                        >
                            Create department
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingDepartment !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeEditModal();
                    }
                }}
            >
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-sm">
                    <DialogHeader className="space-y-1 px-5 pt-5 pb-3">
                        <DialogTitle>Edit department</DialogTitle>
                        <DialogDescription>
                            Update how this department appears for intake.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        className="space-y-3.5 px-5 pb-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (editingDepartment === null) {
                                return;
                            }

                            editForm.patch(
                                `/${team.slug}/organization/departments/${editingDepartment.slug}`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        closeEditModal();
                                    },
                                },
                            );
                        }}
                    >
                        <DepartmentFields idPrefix="edit" form={editForm} />
                        <Button
                            type="submit"
                            className="w-full rounded-xl bg-brand text-white hover:bg-brand/90"
                            disabled={editForm.processing}
                        >
                            Save changes
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function DepartmentFields({
    idPrefix,
    form,
}: {
    idPrefix: string;
    form: {
        data: DepartmentFormState;
        setData: {
            (key: keyof DepartmentFormState, value: string | boolean): void;
        };
        errors: Partial<Record<keyof DepartmentFormState, string>>;
    };
}) {
    return (
        <>
            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}-department-name`}>Name</Label>
                <Input
                    id={`${idPrefix}-department-name`}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="e.g. Cardiology"
                    className="rounded-xl"
                    required
                    autoFocus
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}-department-description`}>
                    Description
                    <span className="ml-1 font-normal text-slate-400">
                        optional
                    </span>
                </Label>
                <Input
                    id={`${idPrefix}-department-description`}
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    placeholder="Short note for staff routing"
                    className="rounded-xl"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 py-2.5">
                <div>
                    <Label
                        htmlFor={`${idPrefix}-department-active`}
                        className="text-sm font-medium text-slate-900"
                    >
                        Active
                    </Label>
                    <p className="text-xs text-slate-500">
                        Show on patient intake
                    </p>
                </div>
                <Switch
                    id={`${idPrefix}-department-active`}
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked)
                    }
                    aria-label="Active department"
                />
            </div>
        </>
    );
}

DepartmentsIndex.layout = (props: { team: { slug: string } }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(props.team.slug),
        },
        {
            title: 'Departments',
            href: `/${props.team.slug}/organization/departments`,
        },
    ],
});
