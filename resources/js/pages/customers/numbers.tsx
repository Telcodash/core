import { AlertCircle } from 'lucide-react';
import {
    CustomerShell,
    customerBreadcrumbs,
} from '@/components/customer-shell';
import { formatMoney } from '@/components/customer-status';
import { NumbersTable } from '@/components/numbers-table';
import type { NumberRow } from '@/components/numbers-table';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { Customer } from '@/pages/customers/details';

type Props = {
    customerId: string;
    customer: Customer | null;
    error: string | null;
    billingError: string | null;
    /** Null when numbers couldn't be loaded (see numbersError / billingError). */
    numbers: NumberRow[] | null;
    numbersError: string | null;
    subscriptionsError: string | null;
};

/** A customer's mobile numbers with tariff, monthly cost and allowances (MSISDN + Subscription Daemons). */
export default function CustomerNumbers({
    customerId,
    customer,
    error,
    billingError,
    numbers,
    numbersError,
    subscriptionsError,
}: Props) {
    return (
        <CustomerShell
            customerId={customerId}
            customer={customer}
            title="Numbers"
            error={error}
        >
            <section className="space-y-3" data-test="numbers-section">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 className="text-lg font-semibold">
                        Mobile numbers
                        {numbers && (
                            <span className="ml-1.5 text-sm font-normal text-muted-foreground">
                                ({numbers.length})
                            </span>
                        )}
                    </h2>
                    {numbers && numbers.length > 0 && (
                        <p className="text-sm text-muted-foreground">
                            Right-click a number, or use its ⋯ button, for its
                            usage.
                        </p>
                    )}
                </div>

                {subscriptionsError && (
                    <Alert>
                        <AlertCircle />
                        <AlertTitle>Tariffs unavailable</AlertTitle>
                        <AlertDescription>
                            {subscriptionsError} Numbers are shown without their
                            tariffs.
                        </AlertDescription>
                    </Alert>
                )}

                {numbers === null ? (
                    <p className="rounded-xl border border-dashed border-primary/20 bg-glass/50 p-4 text-sm text-muted-foreground">
                        {numbersError ??
                            `${billingError ?? 'Billing is unavailable.'} Numbers can't be listed without the customer's accounts.`}
                    </p>
                ) : numbers.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-primary/20 bg-glass/50 p-4 text-sm text-muted-foreground">
                        This customer has no live numbers.
                    </p>
                ) : (
                    <NumbersTable numbers={numbers} customerId={customerId} />
                )}

                {numbers?.some((n) => n.subscription) && (
                    <p className="text-xs text-muted-foreground">
                        Prices are locked when each line starts. Billed{' '}
                        {formatMoney(
                            numbers.reduce(
                                (sum, n) =>
                                    sum +
                                    (n.subscription
                                        ? n.subscription.monthly_price_pence
                                        : 0),
                                0,
                            ),
                        )}{' '}
                        a month, plus any extras.
                    </p>
                )}
            </section>
        </CustomerShell>
    );
}

CustomerNumbers.layout = (
    props: Parameters<typeof customerBreadcrumbs>[0],
) => ({
    breadcrumbs: customerBreadcrumbs(props, [{ title: 'Numbers', href: '#' }]),
});
