import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Building2, ShieldCheck, Users } from 'lucide-react';
import { NavFooter } from '@/components/nav-footer';
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
import { index as teamsIndex } from '@/routes/teams';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

/** The sidebar for the administrator area. Visually tinted so it can't be confused with the main app. */
export function AdminSidebar() {
    const { currentTeam } = usePage().props;

    const navItems: NavItem[] = [
        { title: 'Teams', href: teamsIndex(), icon: Building2 },
        { title: 'Users', href: usersIndex(), icon: Users },
    ];

    const footerItems: NavItem[] = [
        {
            title: 'Back to app',
            href: currentTeam ? dashboard(currentTeam.slug) : '/',
            icon: ArrowLeft,
        },
    ];

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="[--sidebar-accent:oklch(0.93_0.07_85)] [--sidebar:oklch(0.97_0.04_85)] dark:[--sidebar-accent:oklch(0.31_0.05_85)] dark:[--sidebar:oklch(0.24_0.03_85)]"
            data-test="admin-sidebar"
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={teamsIndex()} prefetch>
                                <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-amber-500 text-white">
                                    <ShieldCheck className="size-5" />
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">
                                        Admin Console
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        Organisation-wide
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} label="Administration" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
