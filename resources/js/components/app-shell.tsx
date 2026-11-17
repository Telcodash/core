import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePersistentState } from '@/hooks/use-persistent-state';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const serverDefault = usePage().props.sidebarOpen;
    // Shared by the app and admin layouts, so the sidebar keeps its state when switching between them.
    const [open, setOpen] = usePersistentState('sidebar:open', serverDefault);

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <SidebarProvider open={open} onOpenChange={setOpen}>
            {children}
        </SidebarProvider>
    );
}
