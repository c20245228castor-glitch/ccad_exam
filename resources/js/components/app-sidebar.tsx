import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Droplets, FileText, Gauge, LayoutDashboard, Receipt, Settings, Users, Wallet } from 'lucide-react';
import AppLogo from './app-logo';

const adminNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'Consumers',
        url: '/consumers',
        icon: Users,
    },
    {
        title: 'Water Meters',
        url: '/water-meters',
        icon: Gauge,
    },
    {
        title: 'Water Bills',
        url: '/water-bills',
        icon: Receipt,
    },
    {
        title: 'Water Rates',
        url: '/water-rates',
        icon: FileText,
    },
    {
        title: 'Payments',
        url: '/payments',
        icon: Settings,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: { user: { role: string } } }>().props;
    const mainNavItems: NavItem[] = auth.user.role === 'admin'
        ? adminNavItems
        : [{ title: 'Dashboard', url: '/dashboard', icon: Wallet }];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <Droplets className="size-5 text-cyan-600" />
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
