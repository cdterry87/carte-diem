import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    ArrowUpDown,
    Copy,
    LogOut,
    MoreHorizontal,
    Pencil,
    Share2,
    ShoppingCart,
    Star,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { useLiveReload } from '@/hooks/use-live-reload';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { ItemQuickAdd } from '@/components/lists/item-quick-add';
import { ItemRow } from '@/components/lists/item-row';
import { ShareDialog } from '@/components/lists/share-dialog';
import { SuggestionPanel } from '@/components/suggestions/suggestion-panel';
import { Badge } from '@/components/ui/badge';
import { IconButton } from '@/components/icon-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import listRoutes from '@/routes/lists';
import trips from '@/routes/trips';
import type {
    Category,
    ListItem,
    ListShare,
    ListShareInvitation,
    Recommendation,
    ShoppingList,
    Store,
} from '@/types';

const LIST_EVENTS = [
    'ListItemAdded',
    'ListItemUpdated',
    'ListItemRemoved',
    'ListReordered',
    'TripItemChecked',
    'TripCompleted',
];
const UNPOSITIONED_OFFSET = 100000;
const ALL = 'all';
const NONE = 'none';

type Group = { key: string; name: string; sort: number; items: ListItem[] };

function groupByCategory(
    items: ListItem[],
    order: Record<string, number>,
): Group[] {
    const groups = new Map<string, Group>();

    items.forEach((item) => {
        const key = item.category?.uuid ?? NONE;
        const group = groups.get(key) ?? {
            key,
            name: item.category?.name ?? 'No category',
            sort: item.category
                ? (order[item.category.uuid] ??
                  UNPOSITIONED_OFFSET + item.category.sort_order)
                : Number.MAX_SAFE_INTEGER,
            items: [],
        };

        group.items.push(item);
        groups.set(key, group);
    });

    return [...groups.values()].sort((a, b) => a.sort - b.sort);
}

function ListDetailsDialog({
    list,
    stores,
}: {
    list: ShoppingList;
    stores: Store[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        title: list.title,
        description: list.description ?? '',
        default_store: list.default_store?.uuid ?? NONE,
    });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <IconButton label="Edit list details">
                    <Pencil />
                </IconButton>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>List details</DialogTitle>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            title: data.title,
                            description: data.description || null,
                            default_store:
                                data.default_store === NONE
                                    ? null
                                    : data.default_store,
                        }));
                        form.patch(listRoutes.update.url(list.uuid), {
                            preserveScroll: true,
                            onSuccess: () => setOpen(false),
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="list-title">Name</Label>
                        <Input
                            id="list-title"
                            required
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="list-description">Description</Label>
                        <Input
                            id="list-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Default store</Label>
                        <Select
                            value={form.data.default_store}
                            onValueChange={(value) =>
                                form.setData('default_store', value)
                            }
                        >
                            <SelectTrigger aria-label="Default store">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>No store</SelectItem>
                                {stores.map((store) => (
                                    <SelectItem
                                        key={store.uuid}
                                        value={store.uuid}
                                    >
                                        {store.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.default_store} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ListShow({
    list,
    items,
    categories,
    stores,
    suggestions,
    categoryOrder,
    favoriteProducts,
    shares,
    shareInvitations,
}: {
    list: ShoppingList;
    items: ListItem[];
    categories: Category[];
    stores: Store[];
    suggestions: Recommendation[];
    categoryOrder: Record<string, number>;
    favoriteProducts: string[];
    shares: ListShare[];
    shareInvitations: ListShareInvitation[];
}) {
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState(ALL);
    const [tripStore, setTripStore] = useState(
        list.default_store?.uuid ?? NONE,
    );
    const { auth } = usePage().props;
    useLiveReload(`list.${list.uuid}`, LIST_EVENTS, [
        'list',
        'items',
        'suggestions',
    ]);
    const [reordering, setReordering] = useState(false);
    const editable = list.can.edit_items;
    const options = { preserveScroll: true, preserveState: true };

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        return items.filter(
            (item) =>
                (term === '' || item.name.toLowerCase().includes(term)) &&
                (categoryFilter === ALL ||
                    (item.category?.uuid ?? NONE) === categoryFilter),
        );
    }, [items, search, categoryFilter]);

    const needed = filtered.filter((item) => item.status === 'needed');
    const satisfied = filtered.filter((item) => item.status === 'satisfied');
    const groups = groupByCategory(needed, categoryOrder);
    const hasNeeded = items.some((item) => item.status === 'needed');

    const move = (group: Group, index: number, offset: number) => {
        const ordered = [...items].sort((a, b) => a.sort_order - b.sort_order);
        const first = group.items[index];
        const second = group.items[index + offset];
        const from = ordered.indexOf(first);
        const to = ordered.indexOf(second);

        ordered[from] = second;
        ordered[to] = first;

        router.post(
            listRoutes.items.reorder.url(list.uuid),
            { items: ordered.map((item) => item.uuid) },
            options,
        );
    };

    return (
        <>
            <Head title={list.title} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
                <header className="flex flex-col gap-3">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <h1 className="text-xl font-semibold break-words">
                                {list.title}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {list.owner?.name}
                                {list.default_store &&
                                    ` · ${list.default_store.name}`}
                            </p>
                            {list.description && (
                                <p className="mt-1 text-sm break-words">
                                    {list.description}
                                </p>
                            )}
                        </div>
                        <div className="flex shrink-0 gap-1">
                            <IconButton
                                label={
                                    list.is_favorite
                                        ? 'Remove from favorites'
                                        : 'Add to favorites'
                                }
                                aria-pressed={list.is_favorite}
                                onClick={() =>
                                    router.post(
                                        listRoutes.favorite.url(list.uuid),
                                        {},
                                        options,
                                    )
                                }
                            >
                                <Star
                                    className={
                                        list.is_favorite
                                            ? 'fill-current text-primary'
                                            : ''
                                    }
                                />
                            </IconButton>
                            {list.can.share && (
                                <ShareDialog
                                    listUuid={list.uuid}
                                    shares={shares}
                                    invitations={shareInvitations}
                                    trigger={
                                        <IconButton label="Share list">
                                            <Share2 />
                                        </IconButton>
                                    }
                                />
                            )}
                            {list.can.update && (
                                <>
                                    <ListDetailsDialog
                                        list={list}
                                        stores={stores}
                                    />
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <IconButton label="More list actions">
                                                <MoreHorizontal />
                                            </IconButton>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    router.post(
                                                        listRoutes.duplicate.url(
                                                            list.uuid,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Copy /> Duplicate
                                            </DropdownMenuItem>
                                            {list.status === 'active' ? (
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        router.post(
                                                            listRoutes.archive.url(
                                                                list.uuid,
                                                            ),
                                                            {},
                                                            options,
                                                        )
                                                    }
                                                >
                                                    <Archive /> Archive
                                                </DropdownMenuItem>
                                            ) : (
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        router.post(
                                                            listRoutes.unarchive.url(
                                                                list.uuid,
                                                            ),
                                                            {},
                                                            options,
                                                        )
                                                    }
                                                >
                                                    <ArchiveRestore /> Unarchive
                                                </DropdownMenuItem>
                                            )}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </>
                            )}
                            {list.can.leave && (
                                <ConfirmDialog
                                    trigger={
                                        <IconButton label="Leave this list">
                                            <LogOut />
                                        </IconButton>
                                    }
                                    title="Leave this list?"
                                    description="You lose access right away. Someone with sharing rights would need to invite you again."
                                    confirmLabel="Leave list"
                                    destructive
                                    onConfirm={() =>
                                        router.delete(
                                            listRoutes.shares.destroy.url({
                                                list: list.uuid,
                                                guest: auth.user.uuid,
                                            }),
                                        )
                                    }
                                />
                            )}
                            {list.can.delete && (
                                <ConfirmDialog
                                    trigger={
                                        <IconButton label="Delete list">
                                            <Trash2 />
                                        </IconButton>
                                    }
                                    title="Delete this list?"
                                    description="The list leaves everyone's view. Completed trips and purchase history stay."
                                    confirmLabel="Delete list"
                                    destructive
                                    onConfirm={() =>
                                        router.delete(
                                            listRoutes.destroy.url(list.uuid),
                                        )
                                    }
                                />
                            )}
                        </div>
                    </div>

                    {list.status === 'archived' && (
                        <Badge variant="secondary">Archived</Badge>
                    )}

                    {list.can.run_trip &&
                        (list.active_trip ? (
                            <Button asChild size="lg">
                                <Link href={trips.show(list.active_trip)}>
                                    <ShoppingCart />
                                    Resume trip
                                </Link>
                            </Button>
                        ) : (
                            list.status === 'active' && (
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    {stores.length > 0 && (
                                        <Select
                                            value={tripStore}
                                            onValueChange={setTripStore}
                                        >
                                            <SelectTrigger
                                                className="h-10 w-full sm:w-52"
                                                aria-label="Store for this trip"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>
                                                    No store
                                                </SelectItem>
                                                {stores.map((store) => (
                                                    <SelectItem
                                                        key={store.uuid}
                                                        value={store.uuid}
                                                    >
                                                        {store.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                    <Button
                                        size="lg"
                                        className="sm:flex-1"
                                        disabled={!hasNeeded}
                                        onClick={() =>
                                            router.post(
                                                trips.store.url(list.uuid),
                                                {
                                                    store:
                                                        tripStore === NONE
                                                            ? null
                                                            : tripStore,
                                                    no_store:
                                                        tripStore === NONE,
                                                },
                                            )
                                        }
                                    >
                                        <ShoppingCart />
                                        Start trip
                                    </Button>
                                </div>
                            )
                        ))}
                </header>

                {editable && (
                    <div className="flex flex-col gap-1">
                        <ItemQuickAdd
                            addUrl={listRoutes.items.store.url(list.uuid)}
                            suggestionsUrl={listRoutes.suggestions.url(
                                list.uuid,
                            )}
                        />
                    </div>
                )}

                {editable && suggestions.length > 0 && (
                    <SuggestionPanel
                        suggestions={suggestions}
                        targets={[list]}
                    />
                )}

                {items.length > 0 && (
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input
                            type="search"
                            aria-label="Search this list"
                            placeholder="Search this list"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Select
                            value={categoryFilter}
                            onValueChange={setCategoryFilter}
                        >
                            <SelectTrigger
                                className="w-full sm:w-52"
                                aria-label="Filter by category"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    All categories
                                </SelectItem>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.uuid}
                                        value={category.uuid}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {editable && (
                            <Button
                                variant="outline"
                                className="sm:hidden"
                                aria-pressed={reordering}
                                onClick={() => setReordering(!reordering)}
                            >
                                <ArrowUpDown />
                                {reordering ? 'Done reordering' : 'Reorder'}
                            </Button>
                        )}
                    </div>
                )}

                {items.length === 0 ? (
                    <EmptyState title="This list is empty">
                        {editable && 'Type an item above and press Enter.'}
                    </EmptyState>
                ) : filtered.length === 0 ? (
                    <EmptyState title="No items match your filters" />
                ) : null}

                {groups.map((group) => (
                    <section
                        key={group.key}
                        aria-labelledby={`group-${group.key}`}
                    >
                        <h2
                            id={`group-${group.key}`}
                            className="text-sm font-medium text-muted-foreground"
                        >
                            {group.name}
                        </h2>
                        <ul className="divide-y">
                            {group.items.map((item, index) => (
                                <ItemRow
                                    key={item.uuid}
                                    listUuid={list.uuid}
                                    item={item}
                                    categories={categories}
                                    editable={editable}
                                    favorite={favoriteProducts.includes(
                                        item.product ?? '',
                                    )}
                                    canFavorite={list.can.update}
                                    reordering={reordering}
                                    onMoveUp={
                                        index > 0
                                            ? () => move(group, index, -1)
                                            : undefined
                                    }
                                    onMoveDown={
                                        index < group.items.length - 1
                                            ? () => move(group, index, 1)
                                            : undefined
                                    }
                                />
                            ))}
                        </ul>
                    </section>
                ))}

                {satisfied.length > 0 && (
                    <section aria-labelledby="done-heading">
                        <h2
                            id="done-heading"
                            className="text-sm font-medium text-muted-foreground"
                        >
                            Done ({satisfied.length})
                        </h2>
                        <ul className="divide-y">
                            {satisfied.map((item) => (
                                <ItemRow
                                    key={item.uuid}
                                    listUuid={list.uuid}
                                    item={item}
                                    categories={categories}
                                    editable={editable}
                                    favorite={favoriteProducts.includes(
                                        item.product ?? '',
                                    )}
                                    canFavorite={list.can.update}
                                    reordering={reordering}
                                />
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

ListShow.layout = {
    breadcrumbs: [{ title: 'Lists', href: listRoutes.index() }],
};
