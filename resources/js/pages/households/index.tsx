import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import households from '@/routes/households';
import type { Household } from '@/types';

export default function HouseholdsIndex({
    households: items,
}: {
    households: Household[];
}) {
    const form = useForm({ name: '' });

    return (
        <>
            <Head title="Households" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Households</h1>
                <form
                    className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(households.store.url());
                    }}
                >
                    <div className="flex-1">
                        <Input
                            aria-label="New household name"
                            placeholder="New household name"
                            required
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <InputError
                            message={form.errors.name}
                            className="mt-1"
                        />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        <Plus />
                        Create household
                    </Button>
                </form>
                {items.length === 0 ? (
                    <EmptyState title="No households yet">
                        Create one to share lists and purchase history with
                        family.
                    </EmptyState>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {items.map((household) => (
                            <li key={household.uuid}>
                                <Link
                                    href={households.show(household.uuid)}
                                    className="flex justify-between gap-2 p-3 hover:bg-accent"
                                >
                                    <span className="min-w-0 break-words">
                                        {household.name}
                                    </span>
                                    <span className="shrink-0 text-sm text-muted-foreground capitalize">
                                        {household.role} ·{' '}
                                        {household.member_count} members
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

HouseholdsIndex.layout = {
    breadcrumbs: [{ title: 'Households', href: households.index() }],
};
