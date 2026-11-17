import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Fades and lifts the page in when the visited page component changes. Keyed on the component,
 * not the URL, so in-page visits (search, filters, pagination) keep their state and don't replay it.
 */
export function PageTransition({ children }: { children: ReactNode }) {
    const { component } = usePage();

    return (
        <div key={component} className="flex flex-1 animate-page-in flex-col">
            {children}
        </div>
    );
}
