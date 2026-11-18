import { usePage } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Cake,
    ChevronRight,
    Mail,
    MapPin,
    Phone,
    Smartphone,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { BillingAccount } from '@/components/billing-account-card';
import {
    CustomerShell,
    Detail,
    customerBreadcrumbs,
    useCustomerUrl,
} from '@/components/customer-shell';
import {
    CustomerStatusBadge,
    formatDate,
    formatMoney,
} from '@/components/customer-status';
import { ErrorBoundary } from '@/components/error-boundary';
import { PaneLink, useSidePane } from '@/components/side-pane';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { usage } from '@/routes/customers';
import type { Customer } from '@/pages/customers/details';

type AccountSummary = Omit<BillingAccount, 'invoices'>;

type NumberSummary = {
    msisdn: string;
    national: string;
    classification: string;
    status: 'active' | 'barred';
    barred_reason: string | null;
    friendly_name: string | null;
    account_id: string;
    account_number: string | null;
};

type Props = {
    customerId: string;
    customer: Customer | null;
    error: string | null;
    /** Null when billing couldn't be loaded (see billingError). */
    accounts: AccountSummary[] | null;
    billingError: string | null;
    /** Null when numbers couldn't be loaded (see numbersError / billingError). */
    numbers: NumberSummary[] | null;
    numbersError: string | null;
};

/** Numbers listed on the overview before "and N more". */
const NUMBERS_SHOWN = 4;

/**
 * Every item on the overview is a link that opens its page in the side pane. `data-active` marks
 * the item the pane is showing.
 */
const ROW =
    'group/row -mx-2 block rounded-lg px-2 py-1.5 outline-none transition-[background-color,box-shadow] duration-200 hover:bg-muted/70 focus-visible:ring-2 focus-visible:ring-ring data-active:bg-primary/10 data-active:ring-1 data-active:ring-primary/30';

type Href = ReturnType<ReturnType<typeof useCustomerUrl>>;

/** A summary card that links to the full view, outlined while the pane shows that view. */
function SummaryCard({
    title,
    href,
    linkLabel,
    children,
    test,
}: {
    title: string;
    href: Href;
    linkLabel: string;
    children: ReactNode;
    test: string;
}) {
    const showing = useSidePane()?.isShowing(href.url, 'prefix') ?? false;

    return (
        <Card
            className={cn(
                'gap-4 transition-shadow duration-300',
                showing && 'ring-2 ring-primary/40',
            )}
            data-test={test}
            data-showing={showing || undefined}
        >
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardTitle className="text-base">{title}</CardTitle>
                <PaneLink
                    href={href}
                    className="group/link inline-flex items-center gap-1 rounded-sm text-sm text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                >
                    {linkLabel}
                    <ArrowRight className="size-3.5 transition-transform duration-200 group-hover/link:translate-x-0.5" />
                </PaneLink>
            </CardHeader>
            <CardContent className="text-sm">
                {/* One card failing leaves the other summaries readable. */}
                <ErrorBoundary label={`the ${title.toLowerCase()} summary`}>
                    {children}
                </ErrorBoundary>
            </CardContent>
        </Card>
    );
}

function Unavailable({ children }: { children: ReactNode }) {
    return (
        <p className="flex items-start gap-2 text-muted-foreground">
            <AlertCircle className="mt-0.5 size-4 shrink-0" />
            {children}
        </p>
    );
}

/** A headline figure that opens its view. */
function Figure({
    label,
    value,
    tone,
    href,
}: {
    label: string;
    value: ReactNode;
    tone?: 'danger';
    href: Href;
}) {
    return (
        <PaneLink href={href} className={ROW}>
            <dl>
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd
                    className={cn(
                        'mt-0.5 text-lg font-semibold tabular-nums',
                        tone === 'danger' && 'text-red-700 dark:text-red-400',
                    )}
                >
                    {value}
                </dd>
            </dl>
        </PaneLink>
    );
}

/** A trailing chevron that appears on hover, hinting a row opens something. */
function RowChevron() {
    return (
        <ChevronRight className="size-3.5 shrink-0 text-muted-foreground opacity-0 transition-[opacity,translate] duration-200 group-hover/row:translate-x-0.5 group-hover/row:opacity-100 group-focus-visible/row:opacity-100 group-data-active/row:opacity-100" />
    );
}

/** The customer at a glance: a short summary of each view. Everything opens beside it. */
export default function CustomerOverview({
    customerId,
    customer,
    error,
    accounts,
    billingError,
    numbers,
    numbersError,
}: Props) {
    const url = useCustomerUrl();
    const { currentTeam } = usePage().props;
    const team = currentTeam?.slug ?? '';
    const detailsUrl = url('details', customerId);
    const numbersUrl = url('numbers', customerId);
    const billingUrl = url('billing', customerId);
    const accountUrl = (id: string) => `${billingUrl.url}#${id}`;
    const usageUrl = (msisdn: string) =>
        usage({ current_team: team, customer: customerId, msisdn });

    const open = accounts?.filter((a) => a.status !== 'closed') ?? [];
    const inArrears = open.filter((a) => a.suspension_reason === 'arrears');
    const barred = numbers?.filter((n) => n.status === 'barred') ?? [];
    const nextBill = open
        .map((a) => a.next_bill_on)
        .filter((d): d is string => d !== null)
        .sort()[0];
    const sum = (
        key: 'monthly_charge_pence' | 'balance_pence' | 'overdue_pence',
    ) => open.reduce((total, a) => total + a[key], 0);

    return (
        <CustomerShell
            customerId={customerId}
            customer={customer}
            title="Overview"
            error={error}
        >
            {customer && (
                <div className="space-y-6">
                    {inArrears.length > 0 && (
                        <Alert variant="destructive" data-test="arrears-alert">
                            <AlertTriangle />
                            <AlertTitle>Suspended for arrears</AlertTitle>
                            <AlertDescription>
                                <p>
                                    {inArrears.length === 1 ? (
                                        <>
                                            Account{' '}
                                            <PaneLink
                                                href={accountUrl(
                                                    inArrears[0].id,
                                                )}
                                                className="font-mono underline underline-offset-2"
                                            >
                                                {inArrears[0].account_number}
                                            </PaneLink>{' '}
                                            is
                                        </>
                                    ) : (
                                        `${inArrears.length} accounts are`
                                    )}{' '}
                                    suspended with{' '}
                                    {formatMoney(
                                        inArrears.reduce(
                                            (total, a) =>
                                                total + a.overdue_pence,
                                            0,
                                        ),
                                    )}{' '}
                                    overdue, and{' '}
                                    <PaneLink
                                        href={numbersUrl}
                                        className="underline underline-offset-2"
                                    >
                                        {barred.length === 1
                                            ? '1 number is'
                                            : `${barred.length} numbers are`}
                                    </PaneLink>{' '}
                                    barred.{' '}
                                    <PaneLink
                                        href={billingUrl}
                                        className="font-medium underline underline-offset-2"
                                    >
                                        View billing
                                    </PaneLink>
                                </p>
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="grid gap-4 lg:grid-cols-3">
                        <SummaryCard
                            title="Details"
                            href={detailsUrl}
                            linkLabel="All details"
                            test="overview-details"
                        >
                            <div className="space-y-1">
                                {[
                                    {
                                        icon: <Cake />,
                                        label: 'Date of birth',
                                        value: (
                                            <>
                                                {formatDate(
                                                    customer.date_of_birth,
                                                )}{' '}
                                                <span className="text-muted-foreground">
                                                    (age {customer.age})
                                                </span>
                                            </>
                                        ),
                                    },
                                    {
                                        icon: <Phone />,
                                        label: 'Contact phone',
                                        value: customer.contact_phone,
                                    },
                                    {
                                        icon: <Mail />,
                                        label: 'Email',
                                        value: customer.email,
                                    },
                                    {
                                        icon: <MapPin />,
                                        label: 'Lives in',
                                        value: `${customer.address.town}, ${customer.address.postcode}`,
                                    },
                                ].map((d) => (
                                    <PaneLink
                                        key={d.label}
                                        href={detailsUrl}
                                        className={ROW}
                                    >
                                        <dl>
                                            <Detail
                                                icon={d.icon}
                                                label={d.label}
                                            >
                                                {d.value}
                                            </Detail>
                                        </dl>
                                    </PaneLink>
                                ))}
                            </div>
                        </SummaryCard>

                        <SummaryCard
                            title="Numbers"
                            href={numbersUrl}
                            linkLabel="Tariffs and usage"
                            test="overview-numbers"
                        >
                            {numbers === null ? (
                                <Unavailable>
                                    {numbersError ??
                                        'Numbers need billing, which is unavailable.'}
                                </Unavailable>
                            ) : numbers.length === 0 ? (
                                <p className="text-muted-foreground">
                                    No live numbers.
                                </p>
                            ) : (
                                <>
                                    <div className="mb-2 grid grid-cols-2 gap-x-3">
                                        <Figure
                                            label="Live numbers"
                                            value={numbers.length}
                                            href={numbersUrl}
                                        />
                                        <Figure
                                            label="Barred"
                                            value={barred.length}
                                            href={numbersUrl}
                                            tone={
                                                barred.length > 0
                                                    ? 'danger'
                                                    : undefined
                                            }
                                        />
                                    </div>
                                    <ul className="space-y-0.5">
                                        {numbers
                                            .slice(0, NUMBERS_SHOWN)
                                            .map((n) => (
                                                <li key={n.msisdn}>
                                                    <PaneLink
                                                        href={usageUrl(
                                                            n.msisdn,
                                                        )}
                                                        highlight
                                                        className={cn(
                                                            ROW,
                                                            'flex items-start gap-2',
                                                        )}
                                                        data-test="overview-number"
                                                    >
                                                        <Smartphone className="mt-1 size-3.5 shrink-0 text-muted-foreground" />
                                                        <div className="min-w-0 flex-1">
                                                            {/* Named numbers lead with the name; the number is the subtext. */}
                                                            <div className="flex min-w-0 items-center gap-2">
                                                                <span
                                                                    className={cn(
                                                                        'truncate',
                                                                        n.friendly_name
                                                                            ? 'font-medium'
                                                                            : 'font-mono tabular-nums',
                                                                    )}
                                                                    data-test="overview-number-label"
                                                                >
                                                                    {n.friendly_name ??
                                                                        n.national}
                                                                </span>
                                                                {n.status ===
                                                                    'barred' && (
                                                                    <Badge
                                                                        variant="destructive"
                                                                        className="shrink-0"
                                                                    >
                                                                        Barred
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            {n.friendly_name && (
                                                                <p className="font-mono text-xs text-muted-foreground tabular-nums">
                                                                    {n.national}
                                                                </p>
                                                            )}
                                                        </div>
                                                        {n.account_number && (
                                                            <span
                                                                className="mt-0.5 shrink-0 font-mono text-xs text-muted-foreground"
                                                                title="Billing account"
                                                            >
                                                                {
                                                                    n.account_number
                                                                }
                                                            </span>
                                                        )}
                                                        <span className="mt-1">
                                                            <RowChevron />
                                                        </span>
                                                    </PaneLink>
                                                </li>
                                            ))}
                                    </ul>
                                    {numbers.length > NUMBERS_SHOWN && (
                                        <PaneLink
                                            href={numbersUrl}
                                            className={cn(
                                                ROW,
                                                'mt-1 text-xs text-muted-foreground',
                                            )}
                                        >
                                            and {numbers.length - NUMBERS_SHOWN}{' '}
                                            more
                                        </PaneLink>
                                    )}
                                </>
                            )}
                        </SummaryCard>

                        <SummaryCard
                            title="Billing"
                            href={billingUrl}
                            linkLabel="Accounts and invoices"
                            test="overview-billing"
                        >
                            {accounts === null ? (
                                <Unavailable>{billingError}</Unavailable>
                            ) : accounts.length === 0 ? (
                                <p className="text-muted-foreground">
                                    No billing accounts.
                                </p>
                            ) : (
                                <>
                                    <div className="grid grid-cols-2 gap-x-3">
                                        <Figure
                                            label="Monthly charge"
                                            href={billingUrl}
                                            value={formatMoney(
                                                sum('monthly_charge_pence'),
                                            )}
                                        />
                                        <Figure
                                            label="Balance"
                                            href={billingUrl}
                                            value={formatMoney(
                                                sum('balance_pence'),
                                            )}
                                        />
                                        <Figure
                                            label="Overdue"
                                            href={billingUrl}
                                            value={formatMoney(
                                                sum('overdue_pence'),
                                            )}
                                            tone={
                                                sum('overdue_pence') > 0
                                                    ? 'danger'
                                                    : undefined
                                            }
                                        />
                                        <Figure
                                            label="Next bill"
                                            href={billingUrl}
                                            value={
                                                <span className="text-base">
                                                    {nextBill
                                                        ? formatDate(nextBill)
                                                        : '—'}
                                                </span>
                                            }
                                        />
                                    </div>
                                    <ul className="mt-2 space-y-0.5 border-t pt-2">
                                        {accounts.map((a) => (
                                            <li key={a.id}>
                                                <PaneLink
                                                    href={accountUrl(a.id)}
                                                    highlight
                                                    className={cn(
                                                        ROW,
                                                        'flex items-center justify-between gap-2',
                                                    )}
                                                    data-test="overview-account"
                                                >
                                                    <span className="font-mono text-xs">
                                                        {a.account_number}
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <CustomerStatusBadge
                                                            status={a.status}
                                                        />
                                                        <RowChevron />
                                                    </span>
                                                </PaneLink>
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                        </SummaryCard>
                    </div>
                </div>
            )}
        </CustomerShell>
    );
}

CustomerOverview.layout = (
    props: Parameters<typeof customerBreadcrumbs>[0],
) => ({
    breadcrumbs: customerBreadcrumbs(props),
});
