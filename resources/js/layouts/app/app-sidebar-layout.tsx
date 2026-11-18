import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { PageTransition } from '@/components/page-transition';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import {
    HashScroller,
    isEmbeddedPane,
    PaneBridge,
    SidePaneProvider,
} from '@/components/side-pane';
import { show } from '@/routes/customers';
import type { AppLayoutProps } from '@/types';

/**
 * A customer's views share a header and sub-navigation (CustomerShell), so the layout treats them
 * as one page: moving between them leaves the layout alone and only their content transitions.
 */
function useLayoutTransition(): { transitionKey?: string; scope?: string } {
    const { component, props } = usePage();
    const customerId = props.customerId;

    if (!component.startsWith('customers/') || typeof customerId !== 'string') {
        return {};
    }

    return {
        transitionKey: `customer:${customerId}`,
        scope: show({
            current_team: props.currentTeam?.slug ?? '',
            customer: customerId,
        }).url,
    };
}

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const transition = useLayoutTransition();

    // Inside a side pane the page stands alone: no sidebar or header, the parent page has those.
    if (isEmbeddedPane) {
        return (
            <div className="flex min-h-svh flex-col bg-background">
                <PaneBridge />
                <HashScroller />
                <PageTransition {...transition}>{children}</PageTransition>
            </div>
        );
    }

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <HashScroller />
                <SidePaneProvider>
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    <PageTransition {...transition}>{children}</PageTransition>
                </SidePaneProvider>
            </AppContent>
        </AppShell>
    );
}
