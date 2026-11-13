import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { IconButton } from '@/components/icon-button';
import { Button } from '@/components/ui/button';
import { WorkspaceNav } from '@/components/workspace-nav';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import organize from '@/routes/organize';
import type { Category, Owner, Store } from '@/types';

const rowOptions = { preserveScroll: true, preserveState: true };

function AisleOrderDialog({
    store,
    categories,
    savedOrder,
}: {
    store: Store;
    categories: Category[];
    savedOrder: string[];
}) {
    const initial = () => {
        const position = (category: Category) => {
            const index = savedOrder.indexOf(category.uuid);

            return index === -1
                ? savedOrder.length + category.sort_order
                : index;
        };

        return [...categories].sort((a, b) => position(a) - position(b));
    };
    const [open, setOpen] = useState(false);
    const [ordered, setOrdered] = useState(initial);

    const move = (index: number, offset: number) => {
        const next = [...ordered];
        [next[index], next[index + offset]] = [
            next[index + offset],
            next[index],
        ];
        setOrdered(next);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    setOrdered(initial());
                }
            }}
        >
            <DialogTrigger asChild>
                <IconButton
                    label={`Set aisle order for ${store.name}`}
                    variant="ghost"
                >
                    <ArrowUpDown />
                </IconButton>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Aisle order for {store.name}</DialogTitle>
                    <DialogDescription>
                        Lists and trips at this store sort categories in this
                        order.
                    </DialogDescription>
                </DialogHeader>
                <ol className="max-h-96 divide-y overflow-y-auto">
                    {ordered.map((category, index) => (
                        <li
                            key={category.uuid}
                            className="flex items-center gap-1 py-1"
                        >
                            <span className="min-w-0 flex-1 break-words">
                                {category.name}
                            </span>
                            <IconButton
                                label={`Move ${category.name} up`}
                                variant="ghost"
                                disabled={index === 0}

                                onClick={() => move(index, -1)}
                            >
                                <ArrowUp />
                            </IconButton>
                            <IconButton
                                label={`Move ${category.name} down`}
                                variant="ghost"
                                disabled={index === ordered.length - 1}

                                onClick={() => move(index, 1)}
                            >
                                <ArrowDown />
                            </IconButton>
                        </li>
                    ))}
                </ol>
                <DialogFooter>
                    <Button
                        onClick={() =>
                            router.put(
                                organize.stores.categoryOrder.url(store.uuid),
                                {
                                    categories: ordered.map(
                                        (category) => category.uuid,
                                    ),
                                },
                                {
                                    ...rowOptions,
                                    onSuccess: () => setOpen(false),
                                },
                            )
                        }
                    >
                        Save order
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function StoreRow({
    store,
    categories,
    savedOrder,
}: {
    store: Store;
    categories: Category[];
    savedOrder: string[];
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        name: store.name,
        location: store.location ?? '',
    });

    if (editing) {
        return (
            <li className="p-3">
                <form
                    className="grid gap-2 sm:grid-cols-[1fr_1fr_auto_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            name: data.name,
                            location: data.location || null,
                        }));
                        form.patch(organize.stores.update.url(store.uuid), {
                            ...rowOptions,
                            onSuccess: () => setEditing(false),
                        });
                    }}
                >
                    <Input
                        required
                        aria-label={`New name for ${store.name}`}
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <Input
                        aria-label={`Location for ${store.name}`}
                        placeholder="Location (optional)"
                        value={form.data.location}
                        onChange={(event) =>
                            form.setData('location', event.target.value)
                        }
                    />
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            form.reset();
                            form.clearErrors();
                            setEditing(false);
                        }}
                    >
                        Cancel
                    </Button>
                    <InputError
                        message={form.errors.name}
                        className="sm:col-span-4"
                    />
                </form>
            </li>
        );
    }

    return (
        <li className="flex items-center gap-2 p-3">
            <span className="min-w-0 flex-1 break-words">
                {store.name}
                {store.location && (
                    <span className="block text-sm text-muted-foreground">
                        {store.location}
                    </span>
                )}
            </span>
            <AisleOrderDialog
                store={store}
                categories={categories}
                savedOrder={savedOrder}
            />
            <IconButton
                label={`Edit ${store.name}`}
                variant="ghost"

                onClick={() => {
                    form.setData({
                        name: store.name,
                        location: store.location ?? '',
                    });
                    setEditing(true);
                }}
            >
                <Pencil />
            </IconButton>
            <IconButton
                label={`Remove ${store.name}`}
                variant="ghost"

                onClick={() =>
                    router.delete(
                        organize.stores.destroy.url(store.uuid),
                        rowOptions,
                    )
                }
            >
                <Trash2 />
            </IconButton>
        </li>
    );
}

function CategoryRow({ category }: { category: Category }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ name: category.name });

    if (editing) {
        return (
            <li className="p-3">
                <form
                    className="grid gap-2 sm:grid-cols-[1fr_auto_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            organize.categories.update.url(category.uuid),
                            {
                                ...rowOptions,
                                onSuccess: () => setEditing(false),
                            },
                        );
                    }}
                >
                    <Input
                        required
                        aria-label={`New name for ${category.name}`}
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            form.reset();
                            form.clearErrors();
                            setEditing(false);
                        }}
                    >
                        Cancel
                    </Button>
                    <InputError
                        message={form.errors.name}
                        className="sm:col-span-3"
                    />
                </form>
            </li>
        );
    }

    return (
        <li className="flex min-h-13 items-center gap-2 px-3 py-2">
            <span className="min-w-0 flex-1 break-words">{category.name}</span>
            {category.is_custom ? (
                <>
                    <IconButton
                        label={`Edit ${category.name}`}
                        variant="ghost"

                        onClick={() => {
                            form.setData('name', category.name);
                            setEditing(true);
                        }}
                    >
                        <Pencil />
                    </IconButton>
                    <IconButton
                        label={`Remove ${category.name}`}
                        variant="ghost"

                        onClick={() =>
                            router.delete(
                                organize.categories.destroy.url(category.uuid),
                                rowOptions,
                            )
                        }
                    >
                        <Trash2 />
                    </IconButton>
                </>
            ) : (
                <Badge variant="secondary">Built in</Badge>
            )}
        </li>
    );
}

export default function OrganizeIndex({
    owner,
    stores,
    storeOrders,
    categories,
}: {
    owner: Owner;
    stores: Store[];
    storeOrders: Record<string, string[]>;
    categories: Category[];
}) {
    const ownerPayload = { type: owner.type, uuid: owner.uuid };
    const options = { preserveScroll: true, preserveState: true };
    const storeForm = useForm({ name: '', location: '' });
    const categoryForm = useForm({ name: '' });

    return (
        <>
            <Head title="Stores and categories" />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4">
                <header className="flex flex-col gap-2">
                    <h1 className="text-xl font-semibold">
                        Stores and categories
                    </h1>
                    <WorkspaceNav
                        current={owner.uuid}
                        hrefFor={(query) => organize.index({ query })}
                    />
                </header>

                <section aria-labelledby="stores-heading">
                    <h2 id="stores-heading" className="mb-2 font-medium">
                        Stores
                    </h2>
                    <form
                        className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            storeForm.transform((data) => ({
                                name: data.name,
                                location: data.location || null,
                                owner: ownerPayload,
                            }));
                            storeForm.post(organize.stores.store.url(), {
                                ...options,
                                onSuccess: () => storeForm.reset(),
                            });
                        }}
                    >
                        <Input
                            required
                            aria-label="Store name"
                            placeholder="Store name"
                            value={storeForm.data.name}
                            onChange={(event) =>
                                storeForm.setData('name', event.target.value)
                            }
                        />
                        <Input
                            aria-label="Location (optional)"
                            placeholder="Location (optional)"
                            value={storeForm.data.location}
                            onChange={(event) =>
                                storeForm.setData(
                                    'location',
                                    event.target.value,
                                )
                            }
                        />
                        <Button type="submit" disabled={storeForm.processing}>
                            Add store
                        </Button>
                        <InputError
                            message={storeForm.errors.name}
                            className="sm:col-span-3"
                        />
                    </form>
                    {stores.length > 0 && (
                        <ul className="mt-3 divide-y rounded-lg border">
                            {stores.map((store) => (
                                <StoreRow
                                    key={store.uuid}
                                    store={store}
                                    categories={categories}
                                    savedOrder={storeOrders[store.uuid] ?? []}
                                />
                            ))}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="categories-heading">
                    <h2 id="categories-heading" className="mb-2 font-medium">
                        Categories
                    </h2>
                    <form
                        className="flex flex-col gap-2 sm:flex-row"
                        onSubmit={(event) => {
                            event.preventDefault();
                            categoryForm.transform((data) => ({
                                name: data.name,
                                owner: ownerPayload,
                            }));
                            categoryForm.post(organize.categories.store.url(), {
                                ...options,
                                onSuccess: () => categoryForm.reset(),
                            });
                        }}
                    >
                        <Input
                            required
                            aria-label="Category name"
                            placeholder="Custom category"
                            value={categoryForm.data.name}
                            onChange={(event) =>
                                categoryForm.setData('name', event.target.value)
                            }
                        />
                        <Button
                            type="submit"
                            disabled={categoryForm.processing}
                        >
                            Add category
                        </Button>
                    </form>
                    <InputError
                        message={categoryForm.errors.name}
                        className="mt-1"
                    />
                    <ul className="mt-3 divide-y rounded-lg border">
                        {categories.map((category) => (
                            <CategoryRow
                                key={category.uuid}
                                category={category}
                            />
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

OrganizeIndex.layout = {
    breadcrumbs: [{ title: 'Stores and categories', href: organize.index() }],
};
