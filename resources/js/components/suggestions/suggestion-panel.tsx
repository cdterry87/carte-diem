import { Link, router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { AddToListButton } from '@/components/lists/add-to-list-button';
import { IconButton } from '@/components/icon-button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import staples from '@/routes/staples';
import suggestionRoutes from '@/routes/suggestions';
import type { Recommendation, ShoppingList } from '@/types';

const options = { preserveScroll: true, preserveState: true };

export function SuggestionPanel({
    suggestions,
    targets,
    showOwner = false,
}: {
    suggestions: Recommendation[];
    targets: ShoppingList[];
    showOwner?: boolean;
}) {
    const dismiss = (suggestion: Recommendation, action: string) =>
        router.post(
            suggestionRoutes.dismiss.url(suggestion.product),
            { action },
            options,
        );

    return (
        <section
            aria-labelledby="suggestions-heading"
            className="rounded-lg border p-3"
        >
            <div className="mb-1 flex items-center justify-between gap-2">
                <h2 id="suggestions-heading" className="font-medium">
                    You may need
                </h2>
                <Link
                    href={staples.index()}
                    className="text-sm text-primary underline-offset-4 hover:underline"
                >
                    Staples
                </Link>
            </div>
            <ul className="divide-y">
                {suggestions.map((suggestion) => {
                    const lists = targets.filter(
                        (list) =>
                            list.can.edit_items &&
                            list.owner?.uuid === suggestion.owner?.uuid,
                    );

                    return (
                        <li
                            key={suggestion.product}
                            className="flex items-center gap-2 py-2"
                        >
                            <span className="min-w-0 flex-1 break-words">
                                {suggestion.name}
                                {showOwner && suggestion.owner && (
                                    <span className="ml-2 text-sm text-muted-foreground">
                                        {suggestion.owner.name}
                                    </span>
                                )}
                                <span className="block text-sm text-muted-foreground">
                                    {suggestion.reason}
                                </span>
                            </span>
                            {lists.length > 0 && (
                                <AddToListButton
                                    name={suggestion.name}
                                    targets={lists}
                                />
                            )}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <IconButton
                                        label={`More options for ${suggestion.name}`}
                                        variant="ghost"
                                    >
                                        <MoreHorizontal />
                                    </IconButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            dismiss(suggestion, 'snooze')
                                        }
                                    >
                                        Snooze for 7 days
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            dismiss(suggestion, 'dismiss')
                                        }
                                    >
                                        Dismiss for 30 days
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            dismiss(suggestion, 'mute')
                                        }
                                    >
                                        Stop suggesting this
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
