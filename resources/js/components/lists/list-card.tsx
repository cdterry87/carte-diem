import { Link } from '@inertiajs/react';
import { ShoppingCart, Star } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import lists from '@/routes/lists';
import type { ShoppingList } from '@/types';

export function ListCard({ list }: { list: ShoppingList }) {
    return (
        <Link
            href={lists.show(list.uuid)}
            className="block rounded-lg border bg-card p-4 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="flex items-start justify-between gap-2">
                <h3 className="font-medium break-words">{list.title}</h3>
                {list.is_favorite && (
                    <Star
                        className="size-4 shrink-0 fill-current text-primary"
                        aria-label="Favorite"
                    />
                )}
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
                {list.needed_count ?? 0} needed
                {list.owner && ` · ${list.owner.name}`}
            </p>
            <div className="mt-2 flex flex-wrap gap-1 empty:hidden">
                {list.active_trip && (
                    <Badge>
                        <ShoppingCart /> Trip in progress
                    </Badge>
                )}
                {list.status === 'archived' && (
                    <Badge variant="secondary">Archived</Badge>
                )}
            </div>
        </Link>
    );
}
