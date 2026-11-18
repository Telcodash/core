import { withErrorBoundary } from '@/components/error-boundary';
import {
    AlertTriangle,
    CalendarClock,
    Check,
    ChevronDown,
    CreditCard,
    Landmark,
    MapPin,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    CustomerStatusBadge,
    formatDate,
    formatMoney,
    InvoiceStatusBadge,
} from '@/components/customer-status';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type Address = {
    line_1: string;
    line_2: string | null;
    town: string;
    county: string | null;
    postcode: string;
    country: string;
};

export type Payment = {
    id: string;
    attempt: number;
    initiated_by: 'scheduled' | 'customer';
    method: string;
    amount_pence: number;
    status: 'succeeded' | 'failed';
    failure_code: string | null;
    failure_message: string | null;
    processor_reference: string;
    attempted_on: string;
};

/**
 * plan: a line's monthly price for this invoice's period (in advance); prorata: part of the
 * previous period for a line that started during it; extra: out-of-allowance usage in the
 * previous period (in arrears). Null on invoices from before per-line billing.
 */
export type InvoiceItem = {
    description: string;
    amount_pence: number;
    kind: 'plan' | 'prorata' | 'extra' | null;
    msisdn: string | null;
};

export type Invoice = {
    id: string;
    invoice_number: string;
    period_start: string;
    period_end: string;
    issued_on: string;
    due_on: string;
    line_items: InvoiceItem[];
    amount_pence: number;
    status: string;
    paid_on: string | null;
    payments: Payment[];
};

export type BillingAccount = {
    id: string;
    account_number: string;
    status: string;
    suspension_reason: string | null;
    monthly_charge_pence: number;
    billing_day: number;
    next_bill_on: string | null;
    balance_pence: number;
    overdue_pence: number;
    payment_method: {
        type: string;
        label: string;
        sort_code: string | null;
        mandate_reference: string | null;
        brand: string | null;
        expiry: string | null;
        expired: boolean;
    };
    billing_address: Address | null;
    created_at: string;
    closed_at: string | null;
    invoices: Invoice[];
};

function ordinal(n: number): string {
    const suffix =
        n % 100 >= 11 && n % 100 <= 13
            ? 'th'
            : ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] ?? 'th');

    return `${n}${suffix}`;
}

/** Royal Mail order, post town uppercased. */
export function AddressLines({ address }: { address: Address }) {
    return (
        <address className="leading-relaxed not-italic">
            {[
                address.line_1,
                address.line_2,
                address.town.toUpperCase(),
                address.county,
                address.postcode,
            ]
                .filter(Boolean)
                .map((line) => (
                    <div key={line}>{line}</div>
                ))}
        </address>
    );
}

function Stat({
    label,
    children,
    tone,
}: {
    label: string;
    children: ReactNode;
    tone?: 'danger';
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'mt-0.5 text-lg font-semibold tabular-nums',
                    tone === 'danger' && 'text-red-600 dark:text-red-400',
                )}
            >
                {children}
            </dd>
        </div>
    );
}

function PaymentAttempts({ payments }: { payments: Payment[] }) {
    if (payments.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <ul className="space-y-1">
            {payments.map((p) => (
                <li
                    key={p.id}
                    className="flex items-start gap-1.5 text-xs"
                    title={`${p.processor_reference}${p.failure_message ? ` · ${p.failure_message}` : ''}`}
                >
                    {p.status === 'succeeded' ? (
                        <Check className="mt-px size-3.5 shrink-0 text-emerald-600" />
                    ) : (
                        <X className="mt-px size-3.5 shrink-0 text-red-600" />
                    )}
                    <span>
                        <span className="tabular-nums">
                            {formatDate(p.attempted_on)}
                        </span>
                        {' · '}
                        {p.initiated_by === 'customer'
                            ? 'Paid by customer'
                            : p.method === 'direct_debit'
                              ? 'Direct Debit'
                              : 'Card'}
                        {p.failure_message && (
                            <span className="block text-red-700 dark:text-red-400">
                                {p.failure_message}
                            </span>
                        )}
                    </span>
                </li>
            ))}
        </ul>
    );
}

const sum = (items: InvoiceItem[]) =>
    items.reduce((total, item) => total + item.amount_pence, 0);

/** "447700910123" → "07700 910123" */
const toNational = (msisdn: string) =>
    `0${msisdn.slice(2, 6)} ${msisdn.slice(6)}`;

/** The previous billing period, which part-month and extras charges cover. */
function previousPeriod(periodStart: string): [string, string] {
    const start = new Date(`${periodStart}T00:00:00Z`);
    const from = new Date(start);
    from.setUTCMonth(from.getUTCMonth() - 1);
    const to = new Date(start);
    to.setUTCDate(to.getUTCDate() - 1);

    return [from.toISOString().slice(0, 10), to.toISOString().slice(0, 10)];
}

const ITEM_GROUPS: {
    kind: InvoiceItem['kind'];
    title: (invoice: Invoice) => string;
}[] = [
    {
        kind: 'plan',
        title: (i) =>
            `Monthly plans, ${formatDate(i.period_start)} – ${formatDate(i.period_end)}`,
    },
    {
        kind: 'prorata',
        title: () => 'Part month for new lines',
    },
    {
        kind: 'extra',
        title: (i) => {
            const [from, to] = previousPeriod(i.period_start);

            return `Extras: usage outside allowances, ${formatDate(from)} – ${formatDate(to)}`;
        },
    },
    { kind: null, title: () => 'Charges' },
];

/** An invoice's charges, grouped: plan prices, part months, then extras, each with a subtotal. */
function InvoiceItems({ invoice }: { invoice: Invoice }) {
    const groups = ITEM_GROUPS.map((group) => ({
        ...group,
        items: invoice.line_items.filter(
            (item) => (item.kind ?? null) === group.kind,
        ),
    })).filter((group) => group.items.length > 0);

    return (
        <table className="w-full max-w-2xl text-xs" data-test="invoice-items">
            {groups.map(({ kind, title, items }) => {
                return (
                    <tbody key={kind ?? 'legacy'} data-kind={kind ?? 'legacy'}>
                        <tr>
                            <th
                                colSpan={3}
                                className="pt-2 pb-1 text-left font-medium text-muted-foreground"
                            >
                                {title(invoice)}
                            </th>
                        </tr>
                        {items.map((item, index) => {
                            const number = item.msisdn
                                ? toNational(item.msisdn)
                                : null;
                            const description =
                                number &&
                                item.description.startsWith(`${number} · `)
                                    ? item.description.slice(number.length + 3)
                                    : item.description;

                            return (
                                <tr key={`${item.msisdn}:${index}`}>
                                    <td className="py-0.5 pr-3 font-mono whitespace-nowrap tabular-nums">
                                        {number}
                                    </td>
                                    <td className="py-0.5">{description}</td>
                                    <td className="py-0.5 pl-3 text-right whitespace-nowrap tabular-nums">
                                        {formatMoney(item.amount_pence)}
                                    </td>
                                </tr>
                            );
                        })}
                        {groups.length > 1 && (
                            <tr className="text-muted-foreground">
                                <td colSpan={2} className="pb-1 text-right">
                                    Subtotal
                                </td>
                                <td className="pb-1 pl-3 text-right tabular-nums">
                                    {formatMoney(sum(items))}
                                </td>
                            </tr>
                        )}
                    </tbody>
                );
            })}
            <tfoot>
                <tr className="border-t font-medium">
                    <td colSpan={2} className="pt-1">
                        Total
                    </td>
                    <td className="pt-1 text-right tabular-nums">
                        {formatMoney(invoice.amount_pence)}
                    </td>
                </tr>
            </tfoot>
        </table>
    );
}

function InvoiceRow({ invoice }: { invoice: Invoice }) {
    const [open, setOpen] = useState(false);
    const extras = sum(
        invoice.line_items.filter((item) => item.kind === 'extra'),
    );

    return (
        <>
            <tr className="align-top">
                <td className="px-3 py-2.5">
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        className="inline-flex items-center gap-1 font-mono text-xs hover:underline"
                        aria-expanded={open}
                    >
                        <ChevronDown
                            className={cn(
                                'size-3.5 transition-transform',
                                !open && '-rotate-90',
                            )}
                        />
                        {invoice.invoice_number}
                    </button>
                    <div className="mt-0.5 pl-4.5 text-xs text-muted-foreground">
                        {formatDate(invoice.period_start)} –{' '}
                        {formatDate(invoice.period_end)}
                    </div>
                </td>
                <td className="px-3 py-2.5 text-xs whitespace-nowrap tabular-nums">
                    {formatDate(invoice.due_on)}
                </td>
                <td className="px-3 py-2.5 text-right font-medium tabular-nums">
                    {formatMoney(invoice.amount_pence)}
                    {extras > 0 && (
                        <div
                            className="text-xs font-normal text-amber-700 dark:text-amber-400"
                            title="Usage outside allowances in the previous period"
                        >
                            incl. {formatMoney(extras)} extras
                        </div>
                    )}
                </td>
                <td className="px-3 py-2.5">
                    <InvoiceStatusBadge status={invoice.status} />
                </td>
                <td className="px-3 py-2.5">
                    <PaymentAttempts payments={invoice.payments} />
                </td>
            </tr>
            {open && (
                <tr className="bg-muted/30">
                    <td colSpan={5} className="px-3 py-2.5 pl-8">
                        <InvoiceItems invoice={invoice} />
                    </td>
                </tr>
            )}
        </>
    );
}

function BillingAccountCardContent({ account }: { account: BillingAccount }) {
    const method = account.payment_method;

    return (
        <Card
            id={account.id}
            className={cn(
                'scroll-mt-4',
                account.status === 'closed' && 'opacity-75',
            )}
            data-test="billing-account"
        >
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle className="font-mono">
                            {account.account_number}
                        </CardTitle>
                        <CardDescription className="font-mono text-xs">
                            {account.id}
                        </CardDescription>
                    </div>
                    <CustomerStatusBadge status={account.status} />
                </div>
            </CardHeader>

            <CardContent className="space-y-5">
                {account.suspension_reason === 'arrears' && (
                    <div className="flex items-start gap-2 rounded-md border border-red-600/30 bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        Suspended for arrears. Service resumes automatically
                        once the overdue balance is paid.
                    </div>
                )}

                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Stat label="Balance">
                        {formatMoney(account.balance_pence)}
                    </Stat>
                    <Stat
                        label="Overdue"
                        tone={account.overdue_pence > 0 ? 'danger' : undefined}
                    >
                        {formatMoney(account.overdue_pence)}
                    </Stat>
                    <Stat label="Monthly plan">
                        {formatMoney(account.monthly_charge_pence)}
                    </Stat>
                    <Stat label="Next bill">
                        <span className="text-base">
                            {account.next_bill_on
                                ? formatDate(account.next_bill_on)
                                : '—'}
                        </span>
                    </Stat>
                </dl>

                <dl className="grid gap-4 border-t pt-4 text-sm sm:grid-cols-3">
                    <div className="flex gap-2">
                        {method.type === 'card' ? (
                            <CreditCard className="mt-0.5 size-4 text-muted-foreground" />
                        ) : (
                            <Landmark className="mt-0.5 size-4 text-muted-foreground" />
                        )}
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Payment method
                            </dt>
                            <dd className="mt-0.5">
                                {method.label}
                                {method.type === 'card' && method.expiry && (
                                    <span className="ml-1.5 text-xs text-muted-foreground">
                                        exp {method.expiry.slice(5)}/
                                        {method.expiry.slice(2, 4)}
                                    </span>
                                )}
                                {method.expired && (
                                    <Badge
                                        variant="destructive"
                                        className="ml-1.5"
                                    >
                                        Expired
                                    </Badge>
                                )}
                                {method.sort_code && (
                                    <div className="text-xs text-muted-foreground">
                                        Sort code {method.sort_code} · Mandate{' '}
                                        {method.mandate_reference}
                                    </div>
                                )}
                            </dd>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <CalendarClock className="mt-0.5 size-4 text-muted-foreground" />
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Billing cycle
                            </dt>
                            <dd className="mt-0.5">
                                Billed on the {ordinal(account.billing_day)}
                                <div className="text-xs text-muted-foreground">
                                    Opened {formatDate(account.created_at)}
                                    {account.closed_at &&
                                        ` · Closed ${formatDate(account.closed_at)}`}
                                </div>
                            </dd>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <MapPin className="mt-0.5 size-4 text-muted-foreground" />
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Billing address
                            </dt>
                            <dd className="mt-0.5">
                                {account.billing_address ? (
                                    <AddressLines
                                        address={account.billing_address}
                                    />
                                ) : (
                                    <span className="text-muted-foreground">
                                        Same as customer address
                                    </span>
                                )}
                            </dd>
                        </div>
                    </div>
                </dl>

                <div className="border-t pt-4">
                    <h3 className="mb-2 text-sm font-medium">
                        Recent invoices
                    </h3>
                    {account.invoices.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No invoices yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl glass">
                            <table
                                className="w-full text-sm"
                                data-test="invoice-table"
                            >
                                <thead className="border-b bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Invoice
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Due
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Amount
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Payments
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {account.invoices.map((invoice) => (
                                        <InvoiceRow
                                            key={invoice.id}
                                            invoice={invoice}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export const BillingAccountCard = withErrorBoundary(BillingAccountCardContent, {
    label: 'this billing account',
});
