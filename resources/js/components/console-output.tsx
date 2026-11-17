import { Trash2 } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type ConsoleLevel = 'info' | 'success' | 'warn' | 'error' | 'muted';

export type ConsoleLine = {
    id: number;
    level: ConsoleLevel;
    /** Milliseconds since the run started. */
    elapsed: number;
    text: string;
};

const levelClass: Record<ConsoleLevel, string> = {
    info: 'text-zinc-200',
    success: 'text-emerald-400',
    warn: 'text-amber-400',
    error: 'text-red-400',
    muted: 'text-zinc-500',
};

/** A terminal-style log panel that follows new output. */
export function ConsoleOutput({
    lines,
    running = false,
    onClear,
    className,
}: {
    lines: ConsoleLine[];
    running?: boolean;
    onClear?: () => void;
    className?: string;
}) {
    const scroller = useRef<HTMLDivElement>(null);

    useEffect(() => {
        scroller.current?.scrollTo({ top: scroller.current.scrollHeight });
    }, [lines]);

    return (
        <div
            className={cn(
                'overflow-hidden rounded-lg border border-zinc-800 bg-zinc-950',
                className,
            )}
            data-test="console-output"
        >
            <div className="flex items-center justify-between border-b border-zinc-800 px-3 py-1.5">
                <span className="font-mono text-xs text-zinc-400">
                    console
                    {running && <span className="animate-pulse"> ●</span>}
                </span>
                {onClear && (
                    <Button
                        size="sm"
                        variant="ghost"
                        className="h-6 px-2 text-xs text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
                        onClick={onClear}
                        disabled={running || lines.length === 0}
                    >
                        <Trash2 className="size-3" /> Clear
                    </Button>
                )}
            </div>
            <div
                ref={scroller}
                className="h-80 overflow-auto p-3 font-mono text-xs leading-relaxed"
                role="log"
                aria-live="polite"
            >
                {lines.length === 0 ? (
                    <p className="text-zinc-500">
                        Output from tests will appear here.
                    </p>
                ) : (
                    lines.map((line) => (
                        <div key={line.id} className="flex gap-3">
                            <span className="shrink-0 text-zinc-600 tabular-nums">
                                +{(line.elapsed / 1000).toFixed(2)}s
                            </span>
                            <span
                                className={cn(
                                    'break-all whitespace-pre-wrap',
                                    levelClass[line.level],
                                )}
                            >
                                {line.text}
                            </span>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
