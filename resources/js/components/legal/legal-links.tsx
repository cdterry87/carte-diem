import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import legal from '@/routes/legal';

export function LegalLinks({ className }: { className?: string }) {
    const link = 'underline-offset-4 hover:underline';

    return (
        <nav
            aria-label="Legal"
            className={cn(
                'flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground',
                className,
            )}
        >
            <Link href={legal.privacy()} className={link}>
                Privacy Policy
            </Link>
        </nav>
    );
}
