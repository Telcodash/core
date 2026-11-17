import { AlertCircle } from 'lucide-react';
import { BillingAccountCard } from '@/components/billing-account-card';
import type { BillingAccount } from '@/components/billing-account-card';
import {
    CustomerShell,
    customerBreadcrumbs,
} from '@/components/customer-shell';
import { formatMoney } from '@/components/customer-status';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { Customer } from '@/pages/customers/details';

type Props = {
    customerId: string;
    customer: Customer | null;
    error: string | null;
    /** Null when billing couldn't be loaded (see billingError). */
    accounts: BillingAccount[] | null;
    billingError: string | null;
};

/** A customer's billing accounts, recent invoices and payment attempts (Billing Daemon). */
export default function CustomerBilling({
    customerId,
    customer,
    error,
    accounts,
    billingError,
}: Props) {
    return (
        <CustomerShell
            customerId={customerId}
            customer={customer}
            title="Billing"
            error={error}
        >
            <section className="space-y-3">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 className="text-lg font-semibold">
                        Billing accounts
                        {accounts && (
                            <span className="ml-1.5 text-sm font-normal text-muted-foreground">
                                ({accounts.length})
                            </span>
                        )}
                    </h2>
                    {accounts && accounts.length > 0 && (
                        <p className="text-sm text-muted-foreground">
                            Total balance{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {formatMoney(
                                    accounts.reduce(
                                        (sum, a) => sum + a.balance_pence,
                                        0,
                                    ),
                                )}
                            </span>
                        </p>
                    )}
                </div>

                {billingError && (
                    <Alert data-test="billing-error">
                        <AlertCircle />
                        <AlertTitle>Billing unavailable</AlertTitle>
                        <AlertDescription>{billingError}</AlertDescription>
                    </Alert>
                )}

                {accounts && accounts.length === 0 && (
                    <p className="rounded-xl border border-dashed border-primary/20 bg-glass/50 p-4 text-sm text-muted-foreground">
                        This customer has no billing accounts.
                    </p>
                )}

                {accounts?.map((account) => (
                    <BillingAccountCard key={account.id} account={account} />
                ))}
            </section>
        </CustomerShell>
    );
}

CustomerBilling.layout = (
    props: Parameters<typeof customerBreadcrumbs>[0],
) => ({
    breadcrumbs: customerBreadcrumbs(props, [{ title: 'Billing', href: '#' }]),
});
