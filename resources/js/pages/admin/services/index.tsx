import { Head, router, useHttp, usePoll } from '@inertiajs/react';
import { CheckCircle2, FlaskConical, RefreshCw, XCircle } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { ConsoleOutput } from '@/components/console-output';
import type { ConsoleLevel, ConsoleLine } from '@/components/console-output';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/services';
import { task as taskRoute, test as testRoute } from '@/routes/services/queue';

type Health = {
    reachable: boolean;
    latency_ms: number | null;
    queues: string[];
    error: string | null;
};

type QueueStatus = Health & {
    checked_at: string;
    url: string;
    callback_url: string;
    token_configured: boolean;
    webhook_secret_configured: boolean;
    tasks_24h: Record<string, number>;
};

type Task = {
    id: string;
    queue: string;
    name: string;
    status: string;
    progress: unknown;
    result: unknown;
    error: string | null;
    attempts_made: number;
    updated_at: string | null;
};

type TestResponse = {
    health: Health;
    callback_url?: string;
    task: Task | null;
};

type TaskResponse = {
    task: Task;
    remote: { state: string; error?: string | null } | null;
};

const TERMINAL = ['completed', 'failed', 'cancelled'];
const POLL_MS = 400;
const TIMEOUT_MS = 20_000;

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

const json = (value: unknown) => JSON.stringify(value);

function describeProgress(progress: unknown): string {
    if (
        progress &&
        typeof progress === 'object' &&
        'percent' in progress &&
        typeof progress.percent === 'number'
    ) {
        return `${progress.percent}%`;
    }

    return json(progress);
}

/** Runs the queue round-trip test, writing each step to the console. */
function useQueueTest(onFinished: () => void) {
    const { submit } = useHttp();
    const [lines, setLines] = useState<ConsoleLine[]>([]);
    const [running, setRunning] = useState(false);
    const nextId = useRef(0);
    const unmounted = useRef(false);

    useEffect(() => {
        unmounted.current = false;

        return () => {
            unmounted.current = true;
        };
    }, []);

    const run = useCallback(async () => {
        const started = performance.now();
        const log = (level: ConsoleLevel, text: string) =>
            setLines((prev) => [
                ...prev,
                {
                    id: nextId.current++,
                    level,
                    elapsed: performance.now() - started,
                    text,
                },
            ]);

        setRunning(true);

        if (lines.length > 0) {
            log('muted', '────────────────────────────────────────');
        }

        try {
            log('info', '$ queue round-trip test');
            log('muted', `POST ${testRoute.url()}`);

            const response = (await submit(testRoute())) as TestResponse;
            const { health } = response;

            if (!health.reachable) {
                log('error', `✗ Queue service unreachable: ${health.error}`);
                log(
                    'warn',
                    'Is it running? Start it with `cd ../queue && bun run dev`, and check QUEUE_SERVICE_URL.',
                );

                return;
            }

            log(
                'success',
                `✓ Health check OK (${health.latency_ms}ms) · queues: ${health.queues.join(', ')}`,
            );

            if (!response.task) {
                log('error', '✗ No task was created.');

                return;
            }

            let task: Task = response.task;

            log(
                'info',
                `→ Dispatched ${task.name} to [${task.queue}] as ${task.id}`,
            );
            log('muted', `  webhooks → ${response.callback_url}`);
            log(
                task.status === 'dispatch_failed' || task.status === 'failed'
                    ? 'error'
                    : 'info',
                `  status: ${task.status}${task.error ? ` (${task.error})` : ''}`,
            );

            const deadline = performance.now() + TIMEOUT_MS;

            while (!TERMINAL.includes(task.status)) {
                if (task.status === 'dispatch_failed') {
                    return;
                }

                if (unmounted.current) {
                    return;
                }

                if (performance.now() > deadline) {
                    await diagnose(task);

                    return;
                }

                await sleep(POLL_MS);

                const next: Task = (
                    (await submit(taskRoute(task.id))) as TaskResponse
                ).task;

                if (next.status !== task.status) {
                    log(
                        next.status === 'failed' ? 'error' : 'info',
                        `  status: ${task.status} → ${next.status}` +
                            (next.attempts_made
                                ? ` (attempt ${next.attempts_made})`
                                : ''),
                    );
                }

                if (
                    json(next.progress) !== json(task.progress) &&
                    next.progress !== null
                ) {
                    log(
                        'info',
                        `  progress: ${describeProgress(next.progress)}`,
                    );
                }

                task = next;
            }

            if (task.status === 'completed') {
                log('success', `✓ Completed · result: ${json(task.result)}`);
                log(
                    'success',
                    `✓ Round trip OK in ${((performance.now() - started) / 1000).toFixed(2)}s (Core → queue → worker → webhook → Core)`,
                );
            } else {
                log(
                    'error',
                    `✗ Task ${task.status}: ${task.error ?? 'no error message'}`,
                );
            }
        } catch (error) {
            log(
                'error',
                `✗ Request failed: ${error instanceof Error ? error.message : String(error)}`,
            );
        } finally {
            if (!unmounted.current) {
                setRunning(false);
                onFinished();
            }
        }

        async function diagnose(task: Task) {
            log(
                'warn',
                `! No final webhook after ${TIMEOUT_MS / 1000}s. Asking the queue service directly…`,
            );

            const { remote } = (await submit(
                taskRoute(task.id, { query: { remote: 1 } }),
            )) as TaskResponse;
            const state = remote?.state ?? 'unknown';

            log(
                'info',
                `  queue service says: ${state}${remote?.error ? ` (${remote.error})` : ''}`,
            );

            if (state === 'completed' || state === 'failed') {
                log(
                    'error',
                    '✗ The worker finished but its webhook never reached Core. Check that the callback URL is reachable from the queue service and that WEBHOOK_SECRET matches QUEUE_SERVICE_WEBHOOK_SECRET.',
                );
            } else if (['waiting', 'delayed', 'prioritized'].includes(state)) {
                log(
                    'error',
                    '✗ No worker picked the task up. Is the queue service running with MODE=all or MODE=worker?',
                );
            } else if (state === 'active') {
                log('warn', '! The task is still running on a worker.');
            } else {
                log('error', '✗ The queue service has no record of this task.');
            }
        }
    }, [submit, lines.length, onFinished]);

    return { lines, running, run, clear: () => setLines([]) };
}

function ConfigRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-0.5 sm:flex-row sm:gap-4">
            <dt className="w-40 shrink-0 text-muted-foreground">{label}</dt>
            <dd className="min-w-0 font-mono text-xs break-all sm:text-sm">
                {children}
            </dd>
        </div>
    );
}

function Flag({ ok }: { ok: boolean }) {
    return ok ? (
        <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
            <CheckCircle2 className="size-4" /> configured
        </span>
    ) : (
        <span className="inline-flex items-center gap-1 text-destructive">
            <XCircle className="size-4" /> missing
        </span>
    );
}

export default function Services({ queue }: { queue: QueueStatus }) {
    const [refreshing, setRefreshing] = useState(false);

    const refresh = useCallback(
        () =>
            router.reload({
                only: ['queue'],
                onStart: () => setRefreshing(true),
                onFinish: () => setRefreshing(false),
            }),
        [],
    );

    usePoll(15_000, { only: ['queue'] });

    const { lines, running, run, clear } = useQueueTest(refresh);

    const counts = Object.entries(queue.tasks_24h).filter(([, n]) => n > 0);

    return (
        <>
            <Head title="Services" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Services"
                    description="Connection status and diagnostics for external services"
                />

                <Card data-test="queue-service-card">
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle className="flex items-center gap-2">
                                Queue service
                                {queue.reachable ? (
                                    <Badge className="bg-emerald-600 text-white dark:bg-emerald-500">
                                        <span className="size-1.5 rounded-full bg-white" />
                                        Online
                                    </Badge>
                                ) : (
                                    <Badge variant="destructive">
                                        <span className="size-1.5 rounded-full bg-white" />
                                        Offline
                                    </Badge>
                                )}
                            </CardTitle>
                            <CardDescription>
                                BullMQ task runner. Last checked{' '}
                                {new Date(
                                    queue.checked_at,
                                ).toLocaleTimeString()}
                                {queue.latency_ms !== null &&
                                    ` · ${queue.latency_ms}ms`}
                            </CardDescription>
                        </div>
                        <div className="flex shrink-0 gap-2">
                            <Button
                                variant="outline"
                                onClick={refresh}
                                disabled={refreshing}
                                data-test="refresh-status-button"
                            >
                                {refreshing ? <Spinner /> : <RefreshCw />}
                                Refresh
                            </Button>
                            <Button
                                onClick={run}
                                disabled={running}
                                data-test="test-queue-button"
                            >
                                {running ? <Spinner /> : <FlaskConical />}
                                Test
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-6">
                        {queue.error && (
                            <p className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                                {queue.error}
                            </p>
                        )}

                        <dl className="space-y-2 text-sm">
                            <ConfigRow label="URL">{queue.url}</ConfigRow>
                            <ConfigRow label="Webhook URL">
                                {queue.callback_url}
                            </ConfigRow>
                            <ConfigRow label="Queues">
                                {queue.queues.length > 0
                                    ? queue.queues.join(', ')
                                    : '—'}
                            </ConfigRow>
                            <ConfigRow label="API token">
                                <Flag ok={queue.token_configured} />
                            </ConfigRow>
                            <ConfigRow label="Webhook secret">
                                <Flag ok={queue.webhook_secret_configured} />
                            </ConfigRow>
                            <ConfigRow label="Tasks (24h)">
                                {counts.length > 0 ? (
                                    <span className="flex flex-wrap gap-1.5 font-sans">
                                        {counts.map(([status, n]) => (
                                            <Badge
                                                key={status}
                                                variant="outline"
                                            >
                                                {status.replace('_', ' ')}: {n}
                                            </Badge>
                                        ))}
                                    </span>
                                ) : (
                                    'none'
                                )}
                            </ConfigRow>
                        </dl>

                        <ConsoleOutput
                            lines={lines}
                            running={running}
                            onClear={clear}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Services.layout = {
    breadcrumbs: [
        { title: 'Administration', href: index.url() },
        { title: 'Services', href: index.url() },
    ],
};
