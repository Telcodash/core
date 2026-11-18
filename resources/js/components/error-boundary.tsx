import { AlertTriangle, RotateCcw } from 'lucide-react';
import { Component } from 'react';
import type { ComponentType, ErrorInfo, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    /** What this boundary holds, for the message, e.g. "the numbers table". */
    label?: string;
    /**
     * How much it replaces: a whole page (`page`), a card's content (`section`, the default) or a
     * single line in a list (`inline`).
     */
    size?: 'page' | 'section' | 'inline';
    /** When this changes (e.g. the URL), a caught error is cleared and the children render again. */
    resetKey?: unknown;
    className?: string;
};

type State = { error: Error | null; resetKey: unknown };

/**
 * Client-side error boundary: if rendering what it wraps throws, that part shows an error in place
 * and the rest of the page keeps working. They are nested: the layout keeps the sidebar and header,
 * the customer shell keeps the customer's name and sub-navigation, and cards and tables keep each
 * other. The server has its own boundaries (App\Support\Section) for loading the data.
 */
export class ErrorBoundary extends Component<Props, State> {
    state: State = { error: null, resetKey: this.props.resetKey };

    static getDerivedStateFromError(error: unknown): Partial<State> {
        return {
            error: error instanceof Error ? error : new Error(String(error)),
        };
    }

    static getDerivedStateFromProps(
        props: Props,
        state: State,
    ): Partial<State> | null {
        return Object.is(props.resetKey, state.resetKey)
            ? null
            : { error: null, resetKey: props.resetKey };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error(
            `Error boundary${this.props.label ? ` (${this.props.label})` : ''} caught:`,
            error,
            info.componentStack,
        );
    }

    reset = () => this.setState({ error: null });

    render() {
        const { error } = this.state;

        if (!error) {
            return this.props.children;
        }

        return (
            <ErrorFallback
                error={error}
                label={this.props.label}
                size={this.props.size ?? 'section'}
                onRetry={this.reset}
                className={this.props.className}
            />
        );
    }
}

function ErrorFallback({
    error,
    label,
    size,
    onRetry,
    className,
}: {
    error: Error;
    label?: string;
    size: 'page' | 'section' | 'inline';
    onRetry: () => void;
    className?: string;
}) {
    const what = label ?? (size === 'page' ? 'this page' : 'this section');
    // Details help while developing; in production they'd only confuse (and could leak internals).
    const detail = import.meta.env.DEV ? error.message : null;

    if (size === 'inline') {
        return (
            <p
                role="alert"
                className={cn(
                    'flex items-center gap-1.5 text-xs text-destructive',
                    className,
                )}
                title={detail ?? undefined}
                data-test="error-boundary"
            >
                <AlertTriangle className="size-3.5 shrink-0" />
                Couldn&apos;t show {what}.
                <button
                    type="button"
                    onClick={onRetry}
                    className="underline underline-offset-2 hover:no-underline"
                >
                    Retry
                </button>
            </p>
        );
    }

    return (
        <div
            role="alert"
            className={cn(
                'flex animate-fade-in items-start gap-3 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm',
                size === 'page' && 'm-4 md:m-6',
                className,
            )}
            data-test="error-boundary"
        >
            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-destructive" />
            <div className="min-w-0 flex-1 space-y-1">
                <p className="font-medium">
                    Something went wrong showing {what}.
                </p>
                <p className="text-muted-foreground">
                    {size === 'page'
                        ? 'The rest of the app still works. Try again, or go somewhere else and come back.'
                        : 'The rest of the page is unaffected.'}
                </p>
                {detail && (
                    <p className="font-mono text-xs break-words text-muted-foreground">
                        {detail}
                    </p>
                )}
            </div>
            <Button
                variant="outline"
                size="sm"
                onClick={onRetry}
                className="shrink-0"
            >
                <RotateCcw />
                Try again
            </Button>
        </div>
    );
}

/** `Inner` wrapped in its own error boundary, so it fails on its own wherever it is used. */
export function withErrorBoundary<P extends object>(
    Inner: ComponentType<P>,
    options: Omit<Props, 'children' | 'resetKey'>,
): ComponentType<P> {
    function Bounded(props: P) {
        return (
            <ErrorBoundary {...options}>
                <Inner {...props} />
            </ErrorBoundary>
        );
    }

    Bounded.displayName = `withErrorBoundary(${Inner.displayName ?? Inner.name})`;

    return Bounded;
}
