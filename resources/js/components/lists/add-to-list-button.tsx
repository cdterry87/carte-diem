import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import listRoutes from '@/routes/lists';
import type { ShoppingList } from '@/types';

export function AddToListButton({
    name,
    targets,
    text = 'Add',
    onAdd,
}: {
    name: string;
    targets: ShoppingList[];
    text?: string;
    /** Replaces adding `name` as a single item. */
    onAdd?: (list: ShoppingList) => void;
}) {
    // Starts with the visible text, so voice control users can say what they see.
    const subject = text === 'Add' ? `Add ${name}` : `${text} for ${name}`;
    const add = (list: ShoppingList) =>
        onAdd
            ? onAdd(list)
            : router.post(
                  listRoutes.items.store.url(list.uuid),
                  { name, quantity: null, unit: null },
                  { preserveScroll: true, preserveState: true },
              );

    if (targets.length === 1) {
        return (
            <Button
                size="sm"
                variant="outline"
                aria-label={`${subject} to ${targets[0].title}`}
                onClick={() => add(targets[0])}
            >
                <Plus />
                {text}
            </Button>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    aria-label={`${subject} to a list`}
                >
                    <Plus />
                    {text}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>Add to list</DropdownMenuLabel>
                {targets.map((list) => (
                    <DropdownMenuItem
                        key={list.uuid}
                        onSelect={() => add(list)}
                    >
                        {list.title}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
