import { router } from '@inertiajs/react';
import { ExternalLink, Loader2, Maximize2, X } from 'lucide-react';
import type {
    ComponentProps,
    CSSProperties,
    KeyboardEvent as ReactKeyboardEvent,
    MouseEvent as ReactMouseEvent,
    PointerEvent as ReactPointerEvent,
    ReactNode,
} from 'react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { Button } from '@/components/ui/button';
import { usePersistentState } from '@/hooks/use-persistent-state';
import { cn } from '@/lib/utils';

/**
 * A second page opened beside the current one, in the same browser tab: the focused page and the
 * pane split the content area like windows snapped side by side.
 *
 * The pane is a same-origin iframe named `side-pane`. Inside it the app detects that
 * (`isEmbeddedPane`), drops its sidebar and header, and runs a small bridge (`PaneBridge`) that
 * reports its location and title, takes visits from the parent, and replaces history entries so the
 * pane never adds to the browser's Back history.
 */

const FRAME_NAME = 'side-pane';
const MSG = {
    location: 'side-pane:location',
    close: 'side-pane:close',
    visit: 'side-pane:visit',
} as const;

/** Pane width as a share of the split area, in percent. */
const MIN_WIDTH = 25;
const MAX_WIDTH = 75;
const DEFAULT_WIDTH = 50;

/** Matches the close transition below; also covers reduced motion, where no transitionend fires. */
const CLOSE_MS = 320;

/** True when this document is the page shown inside a side pane. */
export const isEmbeddedPane =
    typeof window !== 'undefined' &&
    window.self !== window.top &&
    window.name === FRAME_NAME;

type Phase = 'closed' | 'opening' | 'open' | 'closing';

type SidePaneApi = {
    open: (url: string) => void;
    close: () => void;
    /** Whether the pane currently shows `url` (path and, if `url` has one, hash). */
    isShowing: (url: string, match?: 'exact' | 'prefix') => boolean;
};

const SidePaneContext = createContext<SidePaneApi | null>(null);

/** The side pane, or null where pages can't open one (inside a pane, or outside the app layout). */
export function useSidePane(): SidePaneApi | null {
    return useContext(SidePaneContext);
}

function toUrl(href: string): URL {
    return new URL(href, window.location.href);
}

function hasOpenOverlay(): boolean {
    return (
        document.querySelector(
            '[role="dialog"], [role="alertdialog"], [role="menu"]',
        ) !== null
    );
}

function stripAppName(title: string): string {
    const at = title.lastIndexOf(' - ');

    return at > 0 ? title.slice(0, at) : title;
}

/** Scroll to the element the URL's hash names (once the page has rendered it) and flash it. */
export function scrollToHash(hash: string, smooth = true): void {
    const id = decodeURIComponent(hash.replace(/^#/, ''));

    if (!id) {
        return;
    }

    let tries = 0;
    const find = () => {
        const el = document.getElementById(id);

        if (!el) {
            if (++tries < 30) {
                requestAnimationFrame(find);
            }

            return;
        }

        const reduce = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        el.scrollIntoView({
            behavior: smooth && !reduce ? 'smooth' : 'auto',
            block: 'start',
        });
        el.animate?.(
            [
                { boxShadow: '0 0 0 3px var(--color-primary)' },
                { boxShadow: '0 0 0 3px transparent' },
            ],
            { duration: reduce ? 0 : 1400, easing: 'ease-out', delay: 250 },
        );
    };

    requestAnimationFrame(find);
}

export function SidePaneProvider({ children }: { children: ReactNode }) {
    const [phase, setPhase] = useState<Phase>('closed');
    /** What the iframe was told to load; a new nonce mounts a fresh iframe (no history entry). */
    const [frame, setFrame] = useState<{ src: string; nonce: number } | null>(
        null,
    );
    /** Where the pane actually is, as reported by its bridge. */
    const [location, setLocation] = useState<{
        href: string;
        title: string;
    } | null>(null);
    const [ready, setReady] = useState(false);
    const [width, setWidth] = usePersistentState(
        'side-pane:width',
        DEFAULT_WIDTH,
    );
    const [dragging, setDragging] = useState(false);
    const [rowWidth, setRowWidth] = useState(0);

    const rowRef = useRef<HTMLDivElement>(null);
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const closeTimer = useRef<number | undefined>(undefined);
    const phaseRef = useRef(phase);
    const readyRef = useRef(ready);
    phaseRef.current = phase;
    readyRef.current = ready;

    const close = useCallback(() => {
        if (phaseRef.current === 'closed' || phaseRef.current === 'closing') {
            return;
        }

        setPhase('closing');
        window.clearTimeout(closeTimer.current);
        closeTimer.current = window.setTimeout(() => {
            setPhase('closed');
            setFrame(null);
            setLocation(null);
            setReady(false);
        }, CLOSE_MS);
    }, []);

    const open = useCallback((href: string) => {
        const url = toUrl(href).href;
        const live =
            phaseRef.current === 'open' || phaseRef.current === 'opening';
        const target = iframeRef.current?.contentWindow;

        // Already open and its page is listening: navigate inside the pane, without reloading the app.
        if (live && target && readyRef.current) {
            target.postMessage(
                { type: MSG.visit, url },
                window.location.origin,
            );
            setLocation((current) =>
                current ? { ...current, href: url } : current,
            );

            return;
        }

        window.clearTimeout(closeTimer.current);
        setFrame((current) => ({ src: url, nonce: (current?.nonce ?? 0) + 1 }));
        setLocation({ href: url, title: '' });
        setReady(false);

        if (live) {
            return; // Still loading: just point the fresh frame at the new page.
        }

        setPhase('opening');
        // Paint once at zero width, then snap open so the width transitions.
        requestAnimationFrame(() =>
            requestAnimationFrame(() =>
                setPhase((p) => (p === 'opening' ? 'open' : p)),
            ),
        );
    }, []);

    const isShowing = useCallback(
        (href: string, match: 'exact' | 'prefix' = 'exact') => {
            if (!location || phase === 'closing' || phase === 'closed') {
                return false;
            }

            const want = toUrl(href);
            const have = toUrl(location.href);

            if (match === 'prefix') {
                return (
                    have.pathname === want.pathname ||
                    have.pathname.startsWith(`${want.pathname}/`)
                );
            }

            return (
                have.pathname === want.pathname &&
                (want.hash === '' || want.hash === have.hash)
            );
        },
        [location, phase],
    );

    // Messages from the page inside the pane.
    useEffect(() => {
        const onMessage = (event: MessageEvent) => {
            if (
                event.origin !== window.location.origin ||
                event.source !== iframeRef.current?.contentWindow
            ) {
                return;
            }

            const data = event.data as {
                type?: string;
                href?: string;
                title?: string;
            };

            if (data.type === MSG.location && data.href) {
                setLocation({ href: data.href, title: data.title ?? '' });
                setReady(true);
            } else if (data.type === MSG.close) {
                close();
            }
        };

        window.addEventListener('message', onMessage);

        return () => window.removeEventListener('message', onMessage);
    }, [close]);

    // Escape closes the pane, unless a dialog or menu is taking it.
    useEffect(() => {
        if (phase === 'closed') {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (
                event.key === 'Escape' &&
                !event.defaultPrevented &&
                !hasOpenOverlay()
            ) {
                close();
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [phase, close]);

    // The panel is sized from the split area's width, so it can slide in at its final size.
    useEffect(() => {
        const row = rowRef.current;

        if (!row) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setRowWidth(entry.contentRect.width),
        );
        observer.observe(row);

        return () => observer.disconnect();
    }, []);

    useEffect(() => () => window.clearTimeout(closeTimer.current), []);

    const api = useMemo(
        () => ({ open, close, isShowing }),
        [open, close, isShowing],
    );

    return (
        <SidePaneContext.Provider value={api}>
            <div ref={rowRef} className="relative flex min-w-0 flex-1">
                <div className="flex min-w-0 flex-1 flex-col">{children}</div>
                {phase !== 'closed' && frame && (
                    <Pane
                        phase={phase}
                        frame={frame}
                        location={location}
                        ready={ready}
                        onReady={() => setReady(true)}
                        width={width}
                        panelWidth={(rowWidth * width) / 100}
                        dragging={dragging}
                        iframeRef={iframeRef}
                        rowRef={rowRef}
                        onResize={setWidth}
                        onDragging={setDragging}
                        onClose={close}
                    />
                )}
            </div>
        </SidePaneContext.Provider>
    );
}

function Pane({
    phase,
    frame,
    location,
    ready,
    onReady,
    width,
    panelWidth,
    dragging,
    iframeRef,
    rowRef,
    onResize,
    onDragging,
    onClose,
}: {
    phase: Phase;
    frame: { src: string; nonce: number };
    location: { href: string; title: string } | null;
    ready: boolean;
    onReady: () => void;
    width: number;
    panelWidth: number;
    dragging: boolean;
    iframeRef: React.RefObject<HTMLIFrameElement | null>;
    rowRef: React.RefObject<HTMLDivElement | null>;
    onResize: (width: number) => void;
    onDragging: (dragging: boolean) => void;
    onClose: () => void;
}) {
    const shown = phase === 'open';
    const href = location?.href ?? frame.src;
    const url = toUrl(href);
    const title = location?.title ? stripAppName(location.title) : 'Loading…';

    const clamp = (value: number) =>
        Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, Math.round(value * 10) / 10));

    const onPointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (event.button !== 0) {
            return;
        }

        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        onDragging(true);
    };

    const onPointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        const row = rowRef.current;

        if (!dragging || !row) {
            return;
        }

        const rect = row.getBoundingClientRect();
        onResize(clamp(((rect.right - event.clientX) / rect.width) * 100));
    };

    const onKeyDown = (event: ReactKeyboardEvent<HTMLDivElement>) => {
        const step = event.shiftKey ? 10 : 5;

        if (event.key === 'ArrowLeft') {
            onResize(clamp(width + step));
        } else if (event.key === 'ArrowRight') {
            onResize(clamp(width - step));
        } else if (event.key === 'Home') {
            onResize(MAX_WIDTH);
        } else if (event.key === 'End') {
            onResize(MIN_WIDTH);
        } else {
            return;
        }

        event.preventDefault();
    };

    // Fallback for pages without the bridge (e.g. an error page): show it once it has loaded.
    const onLoad = () => window.setTimeout(onReady, 400);

    return (
        <aside
            aria-label={`Side pane: ${title}`}
            data-test="side-pane"
            data-state={phase}
            style={
                {
                    '--pane-w': `${width}%`,
                    '--panel-w': `${panelWidth}px`,
                } as CSSProperties
            }
            className={cn(
                'shrink-0 overflow-hidden',
                // Desktop: a column that grows from nothing, pushing the page into its half.
                'md:sticky md:top-2 md:h-[calc(100svh-1rem)] md:self-start',
                shown ? 'md:w-(--pane-w)' : 'md:w-0',
                !dragging &&
                    (phase === 'closing'
                        ? 'md:transition-[width] md:duration-300 md:ease-in-out'
                        : 'md:transition-[width] md:duration-500 md:ease-(--ease-out-expo)'),
                // Phones: a full-screen sheet that slides over the page.
                'max-md:fixed max-md:inset-0 max-md:z-50 max-md:bg-background max-md:transition-transform max-md:duration-300 max-md:ease-(--ease-out-expo)',
                shown ? 'max-md:translate-x-0' : 'max-md:translate-x-full',
                'motion-reduce:transition-none',
            )}
        >
            <div
                className={cn(
                    'absolute inset-y-0 right-0 flex max-md:w-full md:w-(--panel-w) md:p-2',
                    'origin-right transition-[opacity,transform] motion-reduce:transition-none',
                    phase === 'closing'
                        ? 'duration-200 ease-in'
                        : 'duration-500 ease-(--ease-out-expo)',
                    shown
                        ? 'translate-x-0 scale-100 opacity-100'
                        : 'translate-x-6 scale-[0.97] opacity-0',
                )}
            >
                {/* Divider: drag (or arrow keys) to resize, double-click to split evenly. */}
                <div
                    role="separator"
                    aria-orientation="vertical"
                    aria-label="Resize side pane"
                    aria-valuemin={MIN_WIDTH}
                    aria-valuemax={MAX_WIDTH}
                    aria-valuenow={Math.round(width)}
                    tabIndex={0}
                    onPointerDown={onPointerDown}
                    onPointerMove={onPointerMove}
                    onPointerUp={() => onDragging(false)}
                    onPointerCancel={() => onDragging(false)}
                    onDoubleClick={() => onResize(DEFAULT_WIDTH)}
                    onKeyDown={onKeyDown}
                    className="group absolute inset-y-0 left-0 z-10 hidden w-2 cursor-col-resize touch-none items-center justify-center outline-none md:flex"
                >
                    <span
                        className={cn(
                            'h-10 w-1 rounded-full bg-border transition-[height,background-color] duration-200 group-hover:h-16 group-hover:bg-primary/50 group-focus-visible:h-16 group-focus-visible:bg-primary',
                            dragging && 'h-24 bg-primary',
                        )}
                    />
                </div>

                <div
                    className={cn(
                        'relative flex min-w-0 flex-1 flex-col overflow-hidden md:rounded-xl md:border md:shadow-xl',
                        'transition-[background-color,border-color,box-shadow] duration-300',
                        // Until the page is ready the pane reads as a snap preview: a tinted outline.
                        ready
                            ? 'bg-background md:border-border'
                            : 'bg-primary/5 md:border-primary/30 md:shadow-primary/10',
                    )}
                >
                    <header className="flex h-11 shrink-0 items-center gap-1 border-b bg-muted/40 pr-1.5 pl-3">
                        <div className="min-w-0 flex-1">
                            <p
                                className="truncate text-sm font-medium"
                                data-test="side-pane-title"
                            >
                                {title}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label="Open here, full width"
                            title="Open here, full width"
                            onClick={() => {
                                router.visit(
                                    url.pathname + url.search + url.hash,
                                );
                                onClose();
                            }}
                        >
                            <Maximize2 />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            asChild
                        >
                            <a
                                href={href}
                                target="_blank"
                                rel="noopener"
                                aria-label="Open in a new tab"
                                title="Open in a new tab"
                            >
                                <ExternalLink />
                            </a>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label="Close side pane"
                            title="Close (Esc)"
                            onClick={onClose}
                            data-test="side-pane-close"
                        >
                            <X />
                        </Button>
                    </header>

                    <div className="relative min-h-0 flex-1">
                        <iframe
                            key={frame.nonce}
                            ref={iframeRef}
                            name={FRAME_NAME}
                            src={frame.src}
                            title={title}
                            onLoad={onLoad}
                            className={cn(
                                'absolute inset-0 size-full border-0 bg-background transition-opacity duration-300',
                                ready ? 'opacity-100' : 'opacity-0',
                                dragging && 'pointer-events-none',
                            )}
                        />
                        {!ready && (
                            <div className="absolute inset-0 flex animate-fade-in items-center justify-center text-primary/60">
                                <Loader2 className="size-5 animate-spin" />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </aside>
    );
}

/**
 * A link that opens in the side pane where there is one, and navigates normally otherwise (inside
 * a pane, or with a modifier key for a new tab or window). `highlight` marks it while the pane
 * shows exactly its URL.
 */
export function PaneLink({
    href,
    highlight = false,
    onClick,
    ...props
}: Omit<ComponentProps<'a'>, 'href'> & {
    href: string | { url: string };
    highlight?: boolean;
}) {
    const pane = useSidePane();
    const url = typeof href === 'string' ? href : href.url;
    const active = highlight && (pane?.isShowing(url) ?? false);

    return (
        <a
            {...props}
            href={url}
            data-active={active || undefined}
            aria-current={active ? 'true' : undefined}
            onClick={(event: ReactMouseEvent<HTMLAnchorElement>) => {
                onClick?.(event);

                if (
                    event.defaultPrevented ||
                    event.button !== 0 ||
                    event.metaKey ||
                    event.ctrlKey ||
                    event.shiftKey ||
                    event.altKey
                ) {
                    return;
                }

                event.preventDefault();

                if (pane) {
                    pane.open(url);
                } else {
                    router.visit(url);
                }
            }}
        />
    );
}

/** Scrolls to the URL's #hash after the first render and after each visit (pages render client side). */
export function HashScroller() {
    useEffect(() => {
        scrollToHash(window.location.hash, false);

        return router.on('navigate', () => scrollToHash(window.location.hash));
    }, []);

    return null;
}

/** Runs inside a pane: talks to the parent page. */
export function PaneBridge() {
    useEffect(() => {
        const parent = window.parent;
        const origin = window.location.origin;
        const report = () =>
            parent.postMessage(
                {
                    type: MSG.location,
                    href: window.location.href,
                    title: document.title,
                },
                origin,
            );

        // Visits inside the pane replace the current entry: the pane never adds to Back history.
        const offBefore = router.on('before', (event) => {
            event.detail.visit.replace = true;
        });
        const offNavigate = router.on('navigate', () =>
            requestAnimationFrame(report),
        );

        const titleObserver = new MutationObserver(report);
        titleObserver.observe(document.head, {
            subtree: true,
            childList: true,
            characterData: true,
        });

        const onMessage = (event: MessageEvent) => {
            const data = event.data as { type?: string; url?: string };

            if (
                event.source !== parent ||
                event.origin !== origin ||
                data.type !== MSG.visit ||
                !data.url
            ) {
                return;
            }

            const target = new URL(data.url, window.location.href);

            if (target.origin !== origin) {
                return;
            }

            const here = window.location;

            // Same page, another section of it: just scroll there.
            if (
                target.pathname === here.pathname &&
                target.search === here.search &&
                target.hash
            ) {
                window.history.replaceState(
                    window.history.state,
                    '',
                    target.href,
                );
                scrollToHash(target.hash);
                report();

                return;
            }

            router.visit(target.pathname + target.search + target.hash, {
                replace: true,
            });
        };

        const onKey = (event: KeyboardEvent) => {
            if (
                event.key === 'Escape' &&
                !event.defaultPrevented &&
                !hasOpenOverlay()
            ) {
                parent.postMessage({ type: MSG.close }, origin);
            }
        };

        window.addEventListener('message', onMessage);
        window.addEventListener('keydown', onKey);
        report();

        return () => {
            offBefore();
            offNavigate();
            titleObserver.disconnect();
            window.removeEventListener('message', onMessage);
            window.removeEventListener('keydown', onKey);
        };
    }, []);

    return null;
}
