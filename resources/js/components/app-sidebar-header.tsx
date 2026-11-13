import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { NOTIFICATION_EVENTS, useLiveReload } from '@/hooks/use-live-reload';
import notifications from '@/routes/notifications';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth, unreadNotifications } = usePage().props;

    useLiveReload(`user.${auth.user?.uuid}`, NOTIFICATION_EVENTS, [
        'unreadNotifications',
    ]);

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <SidebarTrigger className="-ml-1" />
                    </TooltipTrigger>
                    <TooltipContent>Toggle sidebar</TooltipContent>
                </Tooltip>
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="relative ml-auto"
                    >
                        <Link
                            href={notifications.index()}
                            aria-label={
                                unreadNotifications > 0
                                    ? `Notifications, ${unreadNotifications} unread`
                                    : 'Notifications'
                            }
                        >
                            <Bell />
                            {unreadNotifications > 0 && (
                                <span
                                    aria-hidden="true"
                                    className="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-medium text-primary-foreground"
                                >
                                    {unreadNotifications > 9
                                        ? '9+'
                                        : unreadNotifications}
                                </span>
                            )}
                        </Link>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Notifications</TooltipContent>
            </Tooltip>
        </header>
    );
}
