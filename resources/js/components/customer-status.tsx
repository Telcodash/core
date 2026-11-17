import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const ukDate = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

/** "1978-05-03" or an ISO timestamp → "03 May 1978". Date-only values are read as calendar dates, not UTC midnight. */
export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? new Date(`${value}T12:00:00`)
        : new Date(value);

    return ukDate.format(date);
}

const tone: Record<string, string> = {
    active: 'bg-emerald-500',
    suspended: 'bg-amber-500',
    closed: 'bg-zinc-400 dark:bg-zinc-500',
};

const label = (status: string) =>
    status.charAt(0).toUpperCase() + status.slice(1);

export function AccountStatusDot({ status }: { status: string }) {
    return (
        <span
            className={cn(
                'inline-block size-2 shrink-0 rounded-full',
                tone[status] ?? 'bg-zinc-400',
            )}
            title={label(status)}
            aria-label={label(status)}
        />
    );
}

/** Status badge for customers and billing accounts. */
export function CustomerStatusBadge({ status }: { status: string }) {
    const styles: Record<string, string> = {
        active: 'border-emerald-600/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        suspended:
            'border-amber-600/30 bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        closed: 'border-zinc-400/40 bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
    };

    return (
        <Badge variant="outline" className={styles[status]}>
            <AccountStatusDot status={status} />
            {label(status)}
        </Badge>
    );
}

const gbp = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
});

/** Integer pence → "£12.34". */
export function formatMoney(pence: number): string {
    return gbp.format(pence / 100);
}

export function InvoiceStatusBadge({ status }: { status: string }) {
    const styles: Record<string, string> = {
        paid: 'border-emerald-600/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        open: 'border-sky-600/30 bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
        overdue:
            'border-red-600/30 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    };

    return (
        <Badge variant="outline" className={styles[status]}>
            {label(status)}
        </Badge>
    );
}
