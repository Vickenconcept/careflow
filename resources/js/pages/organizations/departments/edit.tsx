import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import type { Department, Team } from '@/types';

type Props = {
    team: Pick<Team, 'id' | 'name' | 'slug'>;
    department: Department;
};

export default function EditDepartment({ team, department }: Props) {
    const form = useForm({
        name: department.name,
        code: department.code ?? '',
        description: department.description ?? '',
        is_active: department.is_active,
        sort_order: department.sort_order,
    });

    return (
        <>
            <Head title={`Edit ${department.name}`} />

            <div className="flex flex-col space-y-8">
                <Heading
                    title={`Edit ${department.name}`}
                    description="Update department details for this organization."
                />

                <form
                    className="max-w-xl space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            `/${team.slug}/organization/departments/${department.slug}`,
                        );
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="code">Code</Label>
                        <Input
                            id="code"
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData('code', event.target.value)
                            }
                        />
                        <InputError message={form.errors.code} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Input
                            id="description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData(
                                    'description',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="sort_order">Sort order</Label>
                        <Input
                            id="sort_order"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            onChange={(event) =>
                                form.setData(
                                    'sort_order',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError message={form.errors.sort_order} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="size-4 rounded border"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData(
                                    'is_active',
                                    event.target.checked,
                                )
                            }
                        />
                        Active
                    </label>
                    <Button type="submit" disabled={form.processing}>
                        Save department
                    </Button>
                </form>
            </div>
        </>
    );
}

EditDepartment.layout = (props: {
    team: { slug: string };
    department: { name: string; slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(props.team.slug),
        },
        {
            title: 'Departments',
            href: `/${props.team.slug}/organization/departments`,
        },
        {
            title: props.department.name,
            href: `/${props.team.slug}/organization/departments/${props.department.slug}/edit`,
        },
    ],
});
