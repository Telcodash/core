import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Cake,
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { Customer } from '@/pages/customers/details';

type AccountSummary = Omit<BillingAccount, 'invoices'>;

type NumberSummary = {
    msisdn: string;
    national: string;
    classification: string;
    status: 'active' | 'barred';
    barred_reason: string | null;
    account_id: string;
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

/** A summary card that links to the full view. */
function SummaryCard({
    title,
    href,
    linkLabel,
    children,
    test,
}: {
    title: string;
    href: ReturnType<ReturnType<typeof useCustomerUrl>>;
    linkLabel: string;
    children: ReactNode;
    test: string;
}) {
    return (
        <Card className="gap-4" data-test={test}>
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardTitle className="text-base">{title}</CardTitle>
                <Link
                    href={href}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    {linkLabel}
                    <ArrowRight className="size-3.5" />
                </Link>
            </CardHeader>
            <CardContent className="text-sm">{children}</CardContent>
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

function Figure({
    label,
    value,
    tone,
}: {
    label: string;
    value: ReactNode;
    tone?: 'danger';
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'mt-0.5 text-lg font-semibold tabular-nums',
                    tone === 'danger' && 'text-red-700 dark:text-red-400',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

/** The customer at a glance: a short summary of each view, linking to it. */
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
                                    {inArrears.length === 1
                                        ? `Account ${inArrears[0].account_number} is`
                                        : `${inArrears.length} accounts are`}{' '}
                                    suspended with{' '}
                                    {formatMoney(
                                        inArrears.reduce(
                                            (total, a) =>
                                                total + a.overdue_pence,
                                            0,
                                        ),
                                    )}{' '}
                                    overdue, and{' '}
                                    {barred.length === 1
                                        ? '1 number is'
                                        : `${barred.length} numbers are`}{' '}
                                    barred.{' '}
                                    <Link
                                        href={url('billing', customerId)}
                                        className="font-medium underline underline-offset-2"
                                    >
                                        View billing
                                    </Link>
                                </p>
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="grid gap-4 lg:grid-cols-3">
                        <SummaryCard
                            title="Details"
                            href={url('details', customerId)}
                            linkLabel="All details"
                            test="overview-details"
                        >
                            <dl className="space-y-3">
                                <Detail icon={<Cake />} label="Date of birth">
                                    {formatDate(customer.date_of_birth)}{' '}
                                    <span className="text-muted-foreground">
                                        (age {customer.age})
                                    </span>
                                </Detail>
                                <Detail icon={<Phone />} label="Contact phone">
                                    {customer.contact_phone}
                                </Detail>
                                <Detail icon={<Mail />} label="Email">
                                    {customer.email}
                                </Detail>
                                <Detail icon={<MapPin />} label="Lives in">
                                    {customer.address.town},{' '}
                                    {customer.address.postcode}
                                </Detail>
                            </dl>
                        </SummaryCard>

                        <SummaryCard
                            title="Numbers"
                            href={url('numbers', customerId)}
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
                                    <dl className="mb-3 grid grid-cols-2 gap-3">
                                        <Figure
                                            label="Live numbers"
                                            value={numbers.length}
                                        />
                                        <Figure
                                            label="Barred"
                                            value={barred.length}
                                            tone={
                                                barred.length > 0
                                                    ? 'danger'
                                                    : undefined
                                            }
                                        />
                                    </dl>
                                    <ul className="space-y-1.5">
                                        {numbers
                                            .slice(0, NUMBERS_SHOWN)
                                            .map((n) => (
                                                <li
                                                    key={n.msisdn}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Smartphone className="size-3.5 text-muted-foreground" />
                                                    <span className="font-mono tabular-nums">
                                                        {n.national}
                                                    </span>
                                                    {n.status === 'barred' && (
                                                        <Badge variant="destructive">
                                                            Barred
                                                        </Badge>
                                                    )}
                                                </li>
                                            ))}
                                    </ul>
                                    {numbers.length > NUMBERS_SHOWN && (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            and {numbers.length - NUMBERS_SHOWN}{' '}
                                            more
                                        </p>
                                    )}
                                </>
                            )}
                        </SummaryCard>

                        <SummaryCard
                            title="Billing"
                            href={url('billing', customerId)}
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
                                    <dl className="grid grid-cols-2 gap-3">
                                        <Figure
                                            label="Monthly charge"
                                            value={formatMoney(
                                                sum('monthly_charge_pence'),
                                            )}
                                        />
                                        <Figure
                                            label="Balance"
                                            value={formatMoney(
                                                sum('balance_pence'),
                                            )}
                                        />
                                        <Figure
                                            label="Overdue"
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
                                            value={
                                                <span className="text-base">
                                                    {nextBill
                                                        ? formatDate(nextBill)
                                                        : '—'}
                                                </span>
                                            }
                                        />
                                    </dl>
                                    <ul className="mt-3 space-y-1.5 border-t pt-3">
                                        {accounts.map((a) => (
                                            <li
                                                key={a.id}
                                                className="flex items-center justify-between gap-2"
                                            >
                                                <span className="font-mono text-xs">
                                                    {a.account_number}
                                                </span>
                                                <CustomerStatusBadge
                                                    status={a.status}
                                                />
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
