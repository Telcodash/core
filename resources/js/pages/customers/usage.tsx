import { withErrorBoundary } from '@/components/error-boundary';
import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Globe,
    MessageSquare,
    Phone,
    Plane,
    Wifi,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Line,
    LineChart,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    CustomerShell,
    customerBreadcrumbs,
    useCustomerUrl,
} from '@/components/customer-shell';
import type { CustomerHeader } from '@/components/customer-shell';
import { formatDate } from '@/components/customer-status';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { numbers as numbersRoute } from '@/routes/customers';

type Metric = 'minutes' | 'texts' | 'data_mb';

type Allowances = {
    minutes: number | null;
    texts: number | null;
    data_mb: number | null;
    eu_roaming: boolean;
    international_roaming: boolean;
};

type Totals = {
    minutes: number;
    texts: number;
    data_mb: number;
    eu_roaming_days: number;
    world_roaming_days: number;
    international_minutes: number;
};

type Day = {
    date: string;
    minutes: number;
    texts: number;
    data_mb: number;
    roaming: 'eu' | 'world' | null;
    international_minutes: number;
};

type Period = {
    start: string;
    end: string;
    days_in_period: number;
    days_elapsed: number;
    tariff_name: string | null;
    allowances: Allowances | null;
    totals: Totals;
    days: Day[];
};

type Usage = {
    msisdn: string;
    /** Usage covers complete days up to and including this date. */
    through: string;
    tariff_name: string | null;
    /** Oldest first; the last one is the current period, so far. */
    periods: Period[];
};

type Props = {
    customerId: string;
    customer: CustomerHeader;
    number: {
        msisdn: string;
        national: string;
        account_number?: string;
        billing_day?: number;
    };
    usage: Usage | null;
    error: string | null;
};

const count = new Intl.NumberFormat('en-GB');

/** 3540 → "3.5GB", 700 → "700MB" */
function formatData(mb: number): string {
    if (mb < 1024) {
        return `${count.format(Math.round(mb))}MB`;
    }

    const gb = mb / 1024;

    return `${gb >= 100 ? Math.round(gb) : gb.toFixed(1)}GB`;
}

const METRICS: Record<
    Metric,
    {
        label: string;
        icon: ReactNode;
        format: (value: number) => string;
        /** Compact form for axis ticks. */
        tick: (value: number) => string;
    }
> = {
    minutes: {
        label: 'Calls',
        icon: <Phone />,
        format: (v) => `${count.format(v)} mins`,
        tick: (v) => count.format(v),
    },
    texts: {
        label: 'Texts',
        icon: <MessageSquare />,
        format: (v) => `${count.format(v)} ${v === 1 ? 'text' : 'texts'}`,
        tick: (v) => count.format(v),
    },
    data_mb: {
        label: 'Data',
        icon: <Wifi />,
        format: formatData,
        tick: (v) => (v === 0 ? '0' : formatData(v)),
    },
};

const shortDate = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC',
});
const short = (date: string) => shortDate.format(new Date(`${date}T00:00:00Z`));
const periodLabel = (p: Period) => `${short(p.start)} – ${short(p.end)}`;

/** Running total of `metric` for each day of a period. */
function cumulative(period: Period, metric: Metric): number[] {
    let total = 0;

    return period.days.map((day) => (total += day[metric]));
}

function percentChange(now: number, before: number): number | null {
    return before === 0 ? null : Math.round(((now - before) / before) * 100);
}

const tooltipBox =
    'rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md';

function Swatch({ series, dashed }: { series: 1 | 2; dashed?: boolean }) {
    return (
        <span
            aria-hidden
            className="inline-block h-0.5 w-4 shrink-0 rounded-full"
            style={{
                background: dashed
                    ? `repeating-linear-gradient(90deg, var(--series-${series}) 0 4px, transparent 4px 7px)`
                    : `var(--series-${series})`,
            }}
        />
    );
}

/** This period so far against its allowance, and against the same point of the compared period. */
function MetricTileContent({
    metric,
    current,
    compare,
    selected,
    onSelect,
}: {
    metric: Metric;
    current: Period;
    compare: Period | null;
    selected: boolean;
    onSelect: () => void;
}) {
    const { label, icon, format } = METRICS[metric];
    const used = current.totals[metric];
    const allowance = current.allowances?.[metric] ?? null;
    const unlimited = current.allowances !== null && allowance === null;
    const share = allowance ? used / allowance : null;
    const samePoint =
        compare && current.days_elapsed > 0
            ? (cumulative(compare, metric)[
                  Math.min(current.days_elapsed, compare.days.length) - 1
              ] ?? 0)
            : null;
    const change = samePoint === null ? null : percentChange(used, samePoint);

    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={cn(
                'rounded-xl glass p-4 text-left transition-[transform,box-shadow,background-color] duration-300 ease-(--ease-out-expo) hover:-translate-y-0.5 hover:shadow-lg [&_svg]:size-4',
                selected && 'border-foreground/40 ring-1 ring-foreground/20',
            )}
            data-test={`usage-tile-${metric}`}
        >
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                {icon}
                {label}
            </div>
            <div className="mt-2 text-2xl font-semibold tabular-nums">
                {format(used)}
            </div>
            <div className="mt-0.5 text-xs text-muted-foreground">
                {allowance !== null
                    ? `of ${format(allowance)} allowance`
                    : unlimited
                      ? 'Unlimited allowance'
                      : 'No allowance'}
            </div>

            {share !== null && (
                <div
                    className="mt-3 h-1.5 overflow-hidden rounded-full bg-muted"
                    role="meter"
                    aria-label={`${label} used of allowance`}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={Math.round(Math.min(share, 1) * 100)}
                >
                    <div
                        className={cn(
                            'h-full rounded-full',
                            share > 1 && 'bg-amber-500',
                        )}
                        style={{
                            width: `${Math.min(share, 1) * 100}%`,
                            background:
                                share > 1 ? undefined : 'var(--series-1)',
                        }}
                    />
                </div>
            )}

            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                {share !== null && share > 1 && (
                    <span className="inline-flex items-center gap-1 font-medium text-amber-700 dark:text-amber-400 [&_svg]:size-3.5">
                        <AlertTriangle />
                        Over by {format(used - allowance!)}
                    </span>
                )}
                {change !== null && compare && (
                    <span className="text-muted-foreground">
                        <span className="font-medium text-foreground tabular-nums">
                            {change > 0 ? '+' : ''}
                            {change}%
                        </span>{' '}
                        vs same point of {periodLabel(compare)}
                    </span>
                )}
            </div>
        </button>
    );
}

/** Running totals through the period, day by day: the current period against the compared one. */
function ComparisonChartContent({
    metric,
    current,
    compare,
}: {
    metric: Metric;
    current: Period;
    compare: Period;
}) {
    const { format, tick } = METRICS[metric];
    const allowance = current.allowances?.[metric] ?? null;
    const now = cumulative(current, metric);
    const before = cumulative(compare, metric);
    const days = Math.max(current.days_in_period, compare.days_in_period);

    const rows = Array.from({ length: days }, (_, i) => ({
        day: i + 1,
        current: now[i],
        compare: before[i],
        currentDate: current.days[i]?.date,
        compareDate: compare.days[i]?.date,
    }));

    const endLabel =
        (text: string, series: 1 | 2, lastIndex: number) =>
        (props: { x?: number | string; y?: number | string; index?: number }) =>
            props.index === lastIndex ? (
                <text
                    x={Number(props.x) + 6}
                    y={Number(props.y)}
                    dy={4}
                    fontSize={11}
                    className="fill-muted-foreground"
                >
                    <tspan fill={`var(--series-${series})`}>●</tspan> {text}
                </text>
            ) : null;

    return (
        <div className="h-72 w-full" data-test="usage-comparison-chart">
            <ResponsiveContainer>
                <LineChart
                    data={rows}
                    margin={{ top: 8, right: 96, bottom: 4, left: 8 }}
                >
                    <CartesianGrid
                        vertical={false}
                        stroke="currentColor"
                        className="text-border"
                    />
                    <XAxis
                        dataKey="day"
                        tickLine={false}
                        axisLine={false}
                        interval="preserveStartEnd"
                        minTickGap={24}
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                        tickFormatter={(d: number) => `Day ${d}`}
                    />
                    <YAxis
                        tickLine={false}
                        axisLine={false}
                        width={56}
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                        tickFormatter={tick}
                        domain={[
                            0,
                            (max: number) =>
                                Math.max(max, allowance ?? 0) * 1.05,
                        ]}
                    />
                    {allowance !== null && (
                        <ReferenceLine
                            y={allowance}
                            stroke="var(--muted-foreground)"
                            strokeDasharray="4 4"
                            label={{
                                value: `Allowance ${format(allowance)}`,
                                position: 'insideTopLeft',
                                fontSize: 11,
                                fill: 'var(--muted-foreground)',
                            }}
                        />
                    )}
                    <Tooltip
                        cursor={{
                            stroke: 'var(--muted-foreground)',
                            strokeWidth: 1,
                        }}
                        content={({ active, payload }) => {
                            const row = payload?.[0]?.payload as
                                | (typeof rows)[number]
                                | undefined;

                            if (!active || !row) {
                                return null;
                            }

                            return (
                                <div className={tooltipBox}>
                                    <div className="mb-1 font-medium">
                                        Day {row.day} of the period
                                    </div>
                                    {[
                                        {
                                            series: 1 as const,
                                            value: row.current,
                                            date: row.currentDate,
                                        },
                                        {
                                            series: 2 as const,
                                            value: row.compare,
                                            date: row.compareDate,
                                            dashed: true,
                                        },
                                    ].map((s) => (
                                        <div
                                            key={s.series}
                                            className="flex items-center gap-2"
                                        >
                                            <Swatch
                                                series={s.series}
                                                dashed={s.dashed}
                                            />
                                            <span className="text-muted-foreground">
                                                {s.date ? short(s.date) : '—'}
                                            </span>
                                            <span className="ml-auto pl-4 font-medium tabular-nums">
                                                {s.value === undefined
                                                    ? '—'
                                                    : format(s.value)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            );
                        }}
                    />
                    <Line
                        dataKey="compare"
                        name={periodLabel(compare)}
                        stroke="var(--series-2)"
                        strokeWidth={2}
                        strokeDasharray="6 4"
                        dot={false}
                        activeDot={{
                            r: 4,
                            strokeWidth: 2,
                            stroke: 'var(--card)',
                        }}
                        isAnimationActive={false}
                        label={endLabel(
                            short(compare.start),
                            2,
                            before.length - 1,
                        )}
                    />
                    <Line
                        dataKey="current"
                        name={periodLabel(current)}
                        stroke="var(--series-1)"
                        strokeWidth={2}
                        dot={false}
                        activeDot={{
                            r: 4,
                            strokeWidth: 2,
                            stroke: 'var(--card)',
                        }}
                        isAnimationActive={false}
                        label={endLabel('This period', 1, now.length - 1)}
                    />
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
}

/** Each period's total, oldest first; the current period is marked as "so far". */
function PeriodsChartContent({
    metric,
    periods,
    compareIndex,
    onPick,
}: {
    metric: Metric;
    periods: Period[];
    compareIndex: number;
    onPick: (index: number) => void;
}) {
    const { format, tick } = METRICS[metric];
    const current = periods.at(-1)!;
    const allowance = current.allowances?.[metric] ?? null;
    const rows = periods.map((p, i) => ({
        index: i,
        label: short(p.start),
        value: p.totals[metric],
        period: p,
        partial: p.days_elapsed < p.days_in_period,
    }));

    return (
        <div className="h-64 w-full" data-test="usage-periods-chart">
            <ResponsiveContainer>
                <BarChart
                    data={rows}
                    margin={{ top: 8, right: 8, bottom: 4, left: 8 }}
                    barCategoryGap="28%"
                >
                    <CartesianGrid
                        vertical={false}
                        stroke="currentColor"
                        className="text-border"
                    />
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={false}
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                    />
                    <YAxis
                        tickLine={false}
                        axisLine={false}
                        width={56}
                        tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                        tickFormatter={tick}
                        domain={[
                            0,
                            (max: number) =>
                                Math.max(max, allowance ?? 0) * 1.05,
                        ]}
                    />
                    {allowance !== null && (
                        <ReferenceLine
                            y={allowance}
                            stroke="var(--muted-foreground)"
                            strokeDasharray="4 4"
                            label={{
                                value: `Allowance ${format(allowance)}`,
                                position: 'insideTopLeft',
                                fontSize: 11,
                                fill: 'var(--muted-foreground)',
                            }}
                        />
                    )}
                    <Tooltip
                        cursor={{ fill: 'var(--muted)', opacity: 0.5 }}
                        content={({ active, payload }) => {
                            const row = payload?.[0]?.payload as
                                | (typeof rows)[number]
                                | undefined;

                            if (!active || !row) {
                                return null;
                            }

                            const cap = row.period.allowances?.[metric] ?? null;

                            return (
                                <div className={tooltipBox}>
                                    <div className="font-medium">
                                        {periodLabel(row.period)}
                                        {row.partial && ' (so far)'}
                                    </div>
                                    <div className="mt-0.5 tabular-nums">
                                        {format(row.value)}
                                        {cap !== null && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                of {format(cap)}
                                            </span>
                                        )}
                                    </div>
                                    {row.period.tariff_name && (
                                        <div className="text-muted-foreground">
                                            {row.period.tariff_name}
                                        </div>
                                    )}
                                    {!row.partial &&
                                        row.index !== compareIndex && (
                                            <div className="mt-1 text-muted-foreground">
                                                Click to compare with this
                                                period
                                            </div>
                                        )}
                                </div>
                            );
                        }}
                    />
                    <Bar
                        dataKey="value"
                        radius={[4, 4, 0, 0]}
                        isAnimationActive={false}
                        onClick={(_, index) =>
                            index < periods.length - 1 && onPick(index)
                        }
                        className="cursor-pointer"
                    >
                        {rows.map((row) => (
                            <Cell
                                key={row.index}
                                fill={
                                    row.partial
                                        ? 'var(--series-1)'
                                        : row.index === compareIndex
                                          ? 'var(--series-2)'
                                          : 'var(--muted-foreground)'
                                }
                                fillOpacity={
                                    row.partial || row.index === compareIndex
                                        ? 1
                                        : 0.35
                                }
                            />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

/** Every period's numbers: the table view of both charts, plus roaming and international calls. */
function PeriodsTableContent({ periods }: { periods: Period[] }) {
    const cell = (p: Period, metric: Metric) => {
        const cap = p.allowances?.[metric] ?? null;
        const over = cap !== null && p.totals[metric] > cap;

        return (
            <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                {METRICS[metric].format(p.totals[metric])}
                {over && (
                    <span className="ml-1.5 inline-flex items-center gap-0.5 text-xs font-medium text-amber-700 dark:text-amber-400 [&_svg]:size-3">
                        <AlertTriangle />
                        over
                    </span>
                )}
            </td>
        );
    };

    const abroad = (days: number, included: boolean | undefined) =>
        days === 0 ? (
            <span className="text-muted-foreground">—</span>
        ) : (
            <>
                {days} {days === 1 ? 'day' : 'days'}
                {included === false && (
                    <span className="ml-1 text-xs text-muted-foreground">
                        (charged)
                    </span>
                )}
            </>
        );

    return (
        <div className="overflow-x-auto rounded-xl glass">
            <table className="w-full text-sm" data-test="usage-table">
                <thead className="border-b bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th className="px-3 py-2.5 font-medium">Period</th>
                        <th className="px-3 py-2.5 font-medium">Tariff</th>
                        <th className="px-3 py-2.5 text-right font-medium">
                            Calls
                        </th>
                        <th className="px-3 py-2.5 text-right font-medium">
                            Texts
                        </th>
                        <th className="px-3 py-2.5 text-right font-medium">
                            Data
                        </th>
                        <th className="px-3 py-2.5 text-right font-medium">
                            EU roaming
                        </th>
                        <th className="px-3 py-2.5 text-right font-medium">
                            Roaming outside EU
                        </th>
                        <th className="px-3 py-2.5 text-right font-medium">
                            International calls
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {[...periods].reverse().map((p) => (
                        <tr key={p.start}>
                            <td className="px-3 py-2 whitespace-nowrap">
                                {periodLabel(p)}
                                {p.days_elapsed < p.days_in_period && (
                                    <span className="ml-1.5 text-xs text-muted-foreground">
                                        so far ({p.days_elapsed} of{' '}
                                        {p.days_in_period} days)
                                    </span>
                                )}
                            </td>
                            <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                {p.tariff_name ?? '—'}
                            </td>
                            {cell(p, 'minutes')}
                            {cell(p, 'texts')}
                            {cell(p, 'data_mb')}
                            <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                                {abroad(
                                    p.totals.eu_roaming_days,
                                    p.allowances?.eu_roaming,
                                )}
                            </td>
                            <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                                {abroad(
                                    p.totals.world_roaming_days,
                                    p.allowances?.international_roaming,
                                )}
                            </td>
                            <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                                {p.totals.international_minutes === 0 ? (
                                    <span className="text-muted-foreground">
                                        —
                                    </span>
                                ) : (
                                    `${p.totals.international_minutes} mins`
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function BackToNumbers({ customerId }: { customerId: string }) {
    const url = useCustomerUrl();

    return (
        <Link
            href={url('numbers', customerId)}
            className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft className="size-4" /> All numbers
        </Link>
    );
}

export default function NumberUsage({
    customerId,
    customer,
    number,
    usage,
    error,
}: Props) {
    const [metric, setMetric] = useState<Metric>('data_mb');
    const periods = usage?.periods ?? [];
    const [compareIndex, setCompareIndex] = useState(
        Math.max(0, periods.length - 2),
    );
    const current = periods.at(-1) ?? null;
    const compare = periods.length > 1 ? (periods[compareIndex] ?? null) : null;
    const hasUsage = useMemo(
        () =>
            periods.some((p) =>
                p.days.some((d) => d.data_mb + d.minutes + d.texts > 0),
            ),
        [periods],
    );

    return (
        <CustomerShell
            customerId={customerId}
            customer={customer}
            title={`Usage ${number.national}`}
            error={null}
            className="usage-viz"
        >
            <BackToNumbers customerId={customerId} />

            <header className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 className="text-xl font-semibold tracking-tight">
                        Usage{' '}
                        <span className="font-mono" data-test="usage-number">
                            {number.national}
                        </span>
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {[
                            number.account_number &&
                                `Account ${number.account_number}`,
                            usage?.tariff_name,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                {usage && (
                    <p className="text-xs text-muted-foreground">
                        Billing periods start on day {number.billing_day} ·
                        updated through {formatDate(usage.through)}
                    </p>
                )}
            </header>

            {error && (
                <Alert variant="destructive" data-test="usage-error">
                    <AlertCircle />
                    <AlertTitle>Usage unavailable</AlertTitle>
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {usage && current && !hasUsage && (
                <p className="rounded-xl border border-dashed border-primary/20 bg-glass/50 p-4 text-sm text-muted-foreground">
                    No usage recorded for this number in the last{' '}
                    {periods.length} billing periods.
                </p>
            )}

            {usage && current && hasUsage && (
                <>
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">
                            This period{' '}
                            <span className="text-sm font-normal text-muted-foreground">
                                {periodLabel(current)} · day{' '}
                                {current.days_elapsed} of{' '}
                                {current.days_in_period}
                            </span>
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-3">
                            {(['minutes', 'texts', 'data_mb'] as const).map(
                                (m) => (
                                    <MetricTile
                                        key={m}
                                        metric={m}
                                        current={current}
                                        compare={compare}
                                        selected={metric === m}
                                        onSelect={() => setMetric(m)}
                                    />
                                ),
                            )}
                        </div>
                    </section>

                    <div className="flex flex-wrap items-center gap-3">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={metric}
                            onValueChange={(v) => v && setMetric(v as Metric)}
                            aria-label="Usage type"
                        >
                            {(['minutes', 'texts', 'data_mb'] as const).map(
                                (m) => (
                                    <ToggleGroupItem
                                        key={m}
                                        value={m}
                                        className="gap-1.5 px-3 [&_svg]:size-4"
                                    >
                                        {METRICS[m].icon}
                                        {METRICS[m].label}
                                    </ToggleGroupItem>
                                ),
                            )}
                        </ToggleGroup>

                        {compare && (
                            <div className="flex items-center gap-2 text-sm">
                                <span className="text-muted-foreground">
                                    Compare with
                                </span>
                                <Select
                                    value={String(compareIndex)}
                                    onValueChange={(v) =>
                                        setCompareIndex(Number(v))
                                    }
                                >
                                    <SelectTrigger
                                        className="h-9 w-48"
                                        aria-label="Compare with period"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {periods
                                            .slice(0, -1)
                                            .map((p, i) => (
                                                <SelectItem
                                                    key={p.start}
                                                    value={String(i)}
                                                >
                                                    {periodLabel(p)}
                                                </SelectItem>
                                            ))
                                            .reverse()}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>

                    {compare && (
                        <Card>
                            <CardHeader className="pb-0">
                                <CardTitle className="text-base">
                                    {METRICS[metric].label}: this period against{' '}
                                    {periodLabel(compare)}
                                </CardTitle>
                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    <span className="inline-flex items-center gap-1.5">
                                        <Swatch series={1} /> This period (
                                        {periodLabel(current)})
                                    </span>
                                    <span className="inline-flex items-center gap-1.5">
                                        <Swatch series={2} dashed />{' '}
                                        {periodLabel(compare)}
                                    </span>
                                    <span>
                                        Running total by day of the period
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <ComparisonChart
                                    metric={metric}
                                    current={current}
                                    compare={compare}
                                />
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader className="pb-0">
                            <CardTitle className="text-base">
                                {METRICS[metric].label}: last {periods.length}{' '}
                                billing periods
                            </CardTitle>
                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span className="inline-flex items-center gap-1.5">
                                    <span
                                        aria-hidden
                                        className="size-2.5 rounded-sm"
                                        style={{
                                            background: 'var(--series-1)',
                                        }}
                                    />
                                    This period, so far
                                </span>
                                {compare && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <span
                                            aria-hidden
                                            className="size-2.5 rounded-sm"
                                            style={{
                                                background: 'var(--series-2)',
                                            }}
                                        />
                                        Compared ({periodLabel(compare)}): click
                                        a bar to change
                                    </span>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            <PeriodsChart
                                metric={metric}
                                periods={periods}
                                compareIndex={compareIndex}
                                onPick={setCompareIndex}
                            />
                        </CardContent>
                    </Card>

                    <section className="space-y-3">
                        <h2 className="flex items-center gap-2 text-lg font-semibold [&_svg]:size-4 [&_svg]:text-muted-foreground">
                            All periods
                            <Plane />
                            <Globe />
                        </h2>
                        <PeriodsTable periods={periods} />
                        <p className="text-xs text-muted-foreground">
                            Use abroad counts towards the allowances. Roaming
                            days and international calls the tariff doesn&apos;t
                            include, and anything over an allowance, are charged
                            as extras on the next bill.
                        </p>
                    </section>
                </>
            )}
        </CustomerShell>
    );
}

NumberUsage.layout = (
    props: Parameters<typeof customerBreadcrumbs>[0] & {
        number?: Props['number'];
    },
) => {
    const team = props.currentTeam?.slug;

    return {
        breadcrumbs: customerBreadcrumbs(props, [
            {
                title: 'Numbers',
                href:
                    team && props.customerId
                        ? numbersRoute({
                              current_team: team,
                              customer: props.customerId,
                          })
                        : '#',
            },
            {
                title: `Usage ${props.number?.national ?? ''}`.trim(),
                href: '#',
            },
        ]),
    };
};

const MetricTile = withErrorBoundary(MetricTileContent, {
    label: 'this figure',
});

const ComparisonChart = withErrorBoundary(ComparisonChartContent, {
    label: 'this chart',
});

const PeriodsChart = withErrorBoundary(PeriodsChartContent, {
    label: 'this chart',
});

const PeriodsTable = withErrorBoundary(PeriodsTableContent, {
    label: 'the usage table',
});
