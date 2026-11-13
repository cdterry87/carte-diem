import { Link } from '@inertiajs/react';
import {
    ChartColumn,
    History,
    LayoutGrid,
    ListChecks,
    ReceiptText,
    Repeat,
    Store,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { LegalLinks } from '@/components/legal/legal-links';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import history from '@/routes/history';
import households from '@/routes/households';
import lists from '@/routes/lists';
import organize from '@/routes/organize';
import purchases from '@/routes/purchases';
import spending from '@/routes/spending';
import staples from '@/routes/staples';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Lists', href: lists.index(), icon: ListChecks },
    { title: 'Staples', href: staples.index(), icon: Repeat },
    { title: 'History', href: history.index(), icon: History },
    { title: 'Purchases', href: purchases.index(), icon: ReceiptText },
    { title: 'Spending', href: spending.index(), icon: ChartColumn },
    { title: 'Households', href: households.index(), icon: Users },
    { title: 'Stores & categories', href: organize.index(), icon: Store },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <LegalLinks className="px-2 group-data-[collapsible=icon]:hidden" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
