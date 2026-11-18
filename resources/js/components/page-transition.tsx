import { router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { ErrorBoundary } from '@/components/error-boundary';
import { cn } from '@/lib/utils';

/**
 * Whether a visit is taking the user away from what this transition wraps: a GET to another path
 * (outside `scope`, a path prefix, when given). In-page visits such as search, filters, partial
 * reloads and form submissions don't count, so they never fade the page.
 */
function useLeaving(key: string, scope?: string): boolean {
    const [leavingKey, setLeavingKey] = useState<string | null>(null);
    const keyRef = useRef(key);
    keyRef.current = key;

    useEffect(() => {
        const offStart = router.on('start', (event) => {
            const visit = event.detail.visit;

            if (visit.method !== 'get' || visit.only.length > 0) {
                return;
            }

            const to = new URL(String(visit.url), window.location.href);

            if (to.pathname === window.location.pathname) {
                return;
            }

            if (
                scope &&
                (to.pathname === scope || to.pathname.startsWith(`${scope}/`))
            ) {
                return;
            }

            setLeavingKey(keyRef.current);
        });
        const offFinish = router.on('finish', () => setLeavingKey(null));

        return () => {
            offStart();
            offFinish();
        };
    }, [scope]);

    // Tied to the key it was set for, so the next page never renders faded.
    return leavingKey === key;
}

/**
 * Fades the current content out while a visit to another page is in flight, then fades and lifts
 * the new page in. Keyed on the component by default, so in-page visits keep their state and
 * don't replay it; pass `transitionKey` and `scope` to treat a group of pages as one (a customer's
 * views share their header, which stays put while only their content transitions).
 */
export function PageTransition({
    children,
    transitionKey,
    scope,
    className,
    errorLabel,
}: {
    children: ReactNode;
    transitionKey?: string;
    /** Path prefix that this transition stays on: visits within it don't fade it out. */
    scope?: string;
    className?: string;
    /** What the error message calls this content if it crashes; "this page" by default. */
    errorLabel?: string;
}) {
    const { component, url } = usePage();
    const key = transitionKey ?? component;
    const leaving = useLeaving(key, scope);

    return (
        // The keyed element plays the entrance (its animation holds its end state); the inner one
        // fades out on leaving. Their opacities multiply.
        <div key={key} className="flex flex-1 animate-page-in flex-col">
            <div
                className={cn(
                    'flex flex-1 flex-col transition-[opacity,translate] duration-150 ease-in',
                    leaving && '-translate-y-1 opacity-0',
                    className,
                )}
                data-leaving={leaving || undefined}
            >
                {/* Page-level error boundary: a crash in the page leaves the layout around it working.
                    Cleared on the next visit (the URL changes). */}
                <ErrorBoundary size="page" label={errorLabel} resetKey={url}>
                    {children}
                </ErrorBoundary>
            </div>
        </div>
    );
}
