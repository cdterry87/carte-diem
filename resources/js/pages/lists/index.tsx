import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArchiveRestore, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { ListCard } from '@/components/lists/list-card';
import { ScopeFilter } from '@/components/scope-filter';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import listRoutes from '@/routes/lists';
import type { ShoppingList } from '@/types';

type Filters = { scope: string | null; status: string; q: string };

export default function ListsIndex({
    lists,
    filters,
}: {
    lists: ShoppingList[];
    filters: Filters;
}) {
    const { workspaces } = usePage().props;
    const [search, setSearch] = useState(filters.q);
    const form = useForm({
        title: '',
        owner: workspaces[0]?.uuid ?? '',
    });

    const applyFilters = (next: Partial<Filters>) => {
        const merged = { ...filters, q: search, ...next };

        router.get(
            listRoutes.index.url(),
            {
                ...(merged.scope ? { scope: merged.scope } : {}),
                ...(merged.status !== 'active'
                    ? { status: merged.status }
                    : {}),
                ...(merged.q ? { q: merged.q } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Lists" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold">Lists</h1>

                <form
                    className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_auto_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const owner = workspaces.find(
                            (workspace) => workspace.uuid === form.data.owner,
                        );
                        form.transform((data) => ({
                            title: data.title,
                            owner: { type: owner?.type, uuid: owner?.uuid },
                        }));
                        form.post(listRoutes.store.url());
                    }}
                >
                    <Input
                        aria-label="New list name"
                        placeholder="New list name"
                        required
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                    <Select
                        value={form.data.owner}
                        onValueChange={(value) => form.setData('owner', value)}
                    >
                        <SelectTrigger
                            className="w-full sm:w-44"
                            aria-label="List owner"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {workspaces.map((workspace) => (
                                <SelectItem
                                    key={workspace.uuid}
                                    value={workspace.uuid}
                                >
                                    {workspace.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit" disabled={form.processing}>
                        <Plus />
                        Create list
                    </Button>
                    <InputError
                        message={form.errors.title}
                        className="sm:col-span-3"
                    />
                </form>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <form
                        className="flex-1"
                        role="search"
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilters({});
                        }}
                    >
                        <Input
                            type="search"
                            aria-label="Search lists"
                            placeholder="Search lists"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </form>
                    <ScopeFilter
                        value={filters.scope}
                        onChange={(scope) => applyFilters({ scope })}
                    />
                    <Select
                        value={filters.status}
                        onValueChange={(status) => applyFilters({ status })}
                    >
                        <SelectTrigger
                            className="w-full sm:w-36"
                            aria-label="Filter by status"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="archived">Archived</SelectItem>
                            <SelectItem value="deleted">Deleted</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {lists.length === 0 ? (
                    <EmptyState title="No lists here">
                        Create one above, or change the filters.
                    </EmptyState>
                ) : filters.status === 'deleted' ? (
                    <ul className="divide-y rounded-lg border">
                        {lists.map((list) => (
                            <li
                                key={list.uuid}
                                className="flex items-center justify-between gap-2 p-3"
                            >
                                <span className="min-w-0 break-words">
                                    {list.title}
                                    <span className="block text-sm text-muted-foreground">
                                        {list.owner?.name}
                                    </span>
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    aria-label={`Restore ${list.title}`}
                                    onClick={() =>
                                        router.post(
                                            listRoutes.restore.url(list.uuid),
                                        )
                                    }
                                >
                                    <ArchiveRestore />
                                    Restore
                                </Button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {lists.map((list) => (
                            <ListCard key={list.uuid} list={list} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ListsIndex.layout = {
    breadcrumbs: [{ title: 'Lists', href: listRoutes.index() }],
};
