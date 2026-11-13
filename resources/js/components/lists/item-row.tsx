import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ItemEditDialog } from '@/components/lists/item-edit-dialog';
import { Badge } from '@/components/ui/badge';
import { IconButton } from '@/components/icon-button';
import { Checkbox } from '@/components/ui/checkbox';
import { formatAmount } from '@/lib/format';
import { cn } from '@/lib/utils';
import lists from '@/routes/lists';
import products from '@/routes/products';
import type { Category, ListItem } from '@/types';

export function ItemRow({
    listUuid,
    item,
    categories,
    editable,
    favorite,
    canFavorite,
    reordering,
    onMoveUp,
    onMoveDown,
}: {
    listUuid: string;
    item: ListItem;
    categories: Category[];
    editable: boolean;
    favorite: boolean;
    canFavorite: boolean;
    reordering: boolean;
    onMoveUp?: () => void;
    onMoveDown?: () => void;
}) {
    const [pending, setPending] = useState<boolean | null>(null);
    const checked = pending ?? item.status === 'satisfied';
    const amount = formatAmount(item.quantity, item.unit);
    const options = { preserveScroll: true, preserveState: true };
    const movable = onMoveUp !== undefined || onMoveDown !== undefined;
    const arrowClass = reordering ? '' : 'hidden sm:inline-flex';
    const actionClass = reordering && movable ? 'hidden sm:inline-flex' : '';

    const toggle = (next: boolean) => {
        setPending(next);
        router.patch(
            lists.items.update.url({ list: listUuid, item: item.uuid }),
            { status: next ? 'satisfied' : 'needed' },
            { ...options, onFinish: () => setPending(null) },
        );
    };

    const remove = () => {
        router.delete(
            lists.items.destroy.url({ list: listUuid, item: item.uuid }),
            {
                ...options,
                onSuccess: () =>
                    toast(`Removed ${item.name}`, {
                        action: {
                            label: 'Undo',
                            onClick: () =>
                                router.post(
                                    lists.items.restore.url({
                                        list: listUuid,
                                        item: item.uuid,
                                    }),
                                    {},
                                    options,
                                ),
                        },
                    }),
            },
        );
    };

    return (
        <li className="flex items-center gap-1 py-1">
            <label className="flex min-h-11 min-w-0 flex-1 cursor-pointer items-center gap-3 rounded-md px-1">
                <Checkbox
                    checked={checked}
                    disabled={!editable}
                    onCheckedChange={(next) => toggle(next === true)}
                    aria-label={`Mark ${item.name} as ${checked ? 'needed' : 'done'}`}
                    className="size-5"
                />
                <span className="min-w-0 flex-1">
                    <span
                        className={cn(
                            'break-words',
                            checked && 'text-muted-foreground line-through',
                        )}
                    >
                        {item.name}
                    </span>
                    {amount && (
                        <span className="ml-2 text-sm text-muted-foreground">
                            {amount}
                        </span>
                    )}
                    {item.needs_review && (
                        <Badge variant="outline" className="ml-2">
                            Check quantity
                        </Badge>
                    )}
                    {item.notes && (
                        <span className="block text-sm break-words text-muted-foreground">
                            {item.notes}
                        </span>
                    )}
                </span>
            </label>
            {editable && (
                <div className="flex shrink-0 items-center">
                    {movable && (
                        <>
                            <IconButton
                                label={`Move ${item.name} up`}
                                variant="ghost"
                                disabled={!onMoveUp}
                                onClick={onMoveUp}

                                className={arrowClass}
                            >
                                <ArrowUp />
                            </IconButton>
                            <IconButton
                                label={`Move ${item.name} down`}
                                variant="ghost"
                                disabled={!onMoveDown}
                                onClick={onMoveDown}

                                className={arrowClass}
                            >
                                <ArrowDown />
                            </IconButton>
                        </>
                    )}
                    {canFavorite && item.product && (
                        <IconButton
                            label={`${favorite ? 'Remove' : 'Add'} ${item.name} ${favorite ? 'from' : 'to'} favorite items`}
                            variant="ghost"
                            className={actionClass}
                            aria-pressed={favorite}

                            onClick={() =>
                                router.post(
                                    products.favorite.url(item.product ?? ''),
                                    {},
                                    options,
                                )
                            }
                        >
                            <Star
                                className={
                                    favorite ? 'fill-current text-primary' : ''
                                }
                            />
                        </IconButton>
                    )}
                    <ItemEditDialog
                        updateUrl={lists.items.update.url({
                            list: listUuid,
                            item: item.uuid,
                        })}
                        item={item}
                        categories={categories}
                        trigger={
                            <IconButton
                                label={`Edit ${item.name}`}
                                variant="ghost"
                                className={actionClass}
                            >
                                <Pencil />
                            </IconButton>
                        }
                    />
                    <IconButton
                        label={`Remove ${item.name}`}
                        variant="ghost"
                        className={actionClass}
                        onClick={remove}
                    >
                        <Trash2 />
                    </IconButton>
                </div>
            )}
        </li>
    );
}
