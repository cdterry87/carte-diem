import { Link, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export function WorkspaceNav({
    current,
    hrefFor,
}: {
    current: string;
    hrefFor: (
        query: Record<string, string>,
    ) => NonNullable<InertiaLinkProps['href']>;
}) {
    const { workspaces } = usePage().props;

    return (
        <nav aria-label="Workspace" className="flex flex-wrap gap-2">
            {workspaces.map((workspace) => (
                <Link
                    key={workspace.uuid}
                    href={hrefFor(
                        workspace.type === 'household'
                            ? { household: workspace.uuid }
                            : {},
                    )}
                    aria-current={
                        workspace.uuid === current ? 'page' : undefined
                    }
                    className={cn(
                        'rounded-md border px-3 py-1.5 text-sm',
                        workspace.uuid === current
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-accent',
                    )}
                >
                    {workspace.name}
                </Link>
            ))}
        </nav>
    );
}
