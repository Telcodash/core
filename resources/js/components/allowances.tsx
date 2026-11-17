import {
    Check,
    Globe,
    MessageSquare,
    Phone,
    Plane,
    Signal,
    Wifi,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** What a tariff includes each month. Null quantities mean unlimited. */
export type Allowances = {
    minutes: number | null;
    texts: number | null;
    data_mb: number | null;
    eu_roaming: boolean;
    international_roaming: boolean;
    five_g_plus: boolean;
};

const number = new Intl.NumberFormat('en-GB');

export function formatMinutes(minutes: number | null): string {
    return minutes === null ? 'Unlimited' : `${number.format(minutes)} mins`;
}

export function formatTexts(texts: number | null): string {
    return texts === null ? 'Unlimited' : `${number.format(texts)} texts`;
}

/** 2048 → "2GB", 512 → "512MB", null → "Unlimited". */
export function formatData(mb: number | null): string {
    if (mb === null) {
        return 'Unlimited';
    }

    if (mb < 1024) {
        return `${mb}MB`;
    }

    const gb = mb / 1024;

    return `${Number.isInteger(gb) ? gb : gb.toFixed(1)}GB`;
}

function Quantity({
    icon,
    label,
    value,
}: {
    icon: ReactNode;
    label: string;
    value: string;
}) {
    return (
        <span
            className="inline-flex items-center gap-1 whitespace-nowrap [&>svg]:size-3.5 [&>svg]:text-muted-foreground"
            title={label}
        >
            {icon}
            <span className="sr-only">{label}: </span>
            {value}
        </span>
    );
}

function Feature({
    icon,
    label,
    included,
}: {
    icon: ReactNode;
    label: string;
    included: boolean;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs whitespace-nowrap [&>svg]:size-3',
                included
                    ? 'border-emerald-600/30 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                    : 'border-transparent text-muted-foreground line-through decoration-muted-foreground/50',
            )}
            title={`${label}: ${included ? 'included' : 'not included'}`}
        >
            {included ? <Check /> : <X />}
            {icon}
            {label}
        </span>
    );
}

/** Compact rendering of a tariff's allowances: quantities, then included features. */
export function AllowanceList({
    allowances,
    className,
}: {
    allowances: Allowances;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <div className="flex flex-wrap gap-x-3 gap-y-1 text-sm">
                <Quantity
                    icon={<Phone />}
                    label="Calls"
                    value={formatMinutes(allowances.minutes)}
                />
                <Quantity
                    icon={<MessageSquare />}
                    label="Texts"
                    value={formatTexts(allowances.texts)}
                />
                <Quantity
                    icon={<Wifi />}
                    label="Data"
                    value={`${formatData(allowances.data_mb)} data`}
                />
            </div>
            <div className="flex flex-wrap gap-1">
                <Feature
                    icon={<Plane />}
                    label="EU roaming"
                    included={allowances.eu_roaming}
                />
                <Feature
                    icon={<Globe />}
                    label="Intl roaming"
                    included={allowances.international_roaming}
                />
                <Feature
                    icon={<Signal />}
                    label="5G+"
                    included={allowances.five_g_plus}
                />
            </div>
        </div>
    );
}
