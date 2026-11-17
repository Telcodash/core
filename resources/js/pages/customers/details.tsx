import { Cake, Mail, MapPin, Phone } from 'lucide-react';
import { AddressLines } from '@/components/billing-account-card';
import type { Address } from '@/components/billing-account-card';
import {
    CustomerShell,
    Detail,
    customerBreadcrumbs,
} from '@/components/customer-shell';
import type { CustomerHeader } from '@/components/customer-shell';
import { formatDate } from '@/components/customer-status';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type Customer = CustomerHeader & {
    full_name: string;
    status: string;
    created_at: string;
    title: string | null;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    age: number;
    email: string;
    contact_phone: string;
    address: Address;
    updated_at: string;
};

type Props = {
    customerId: string;
    customer: Customer | null;
    error: string | null;
};

/** A customer's personal details (Customer Account Daemon). */
export default function CustomerDetails({
    customerId,
    customer,
    error,
}: Props) {
    return (
        <CustomerShell
            customerId={customerId}
            customer={customer}
            title="Details"
            error={error}
        >
            {customer && (
                <Card data-test="personal-details">
                    <CardHeader>
                        <CardTitle>Personal details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-5 text-sm sm:grid-cols-2">
                            <Detail icon={<Cake />} label="Date of birth">
                                {formatDate(customer.date_of_birth)}{' '}
                                <span className="text-muted-foreground">
                                    (age {customer.age})
                                </span>
                            </Detail>
                            <Detail icon={<Phone />} label="Contact phone">
                                <a
                                    href={`tel:${customer.contact_phone.replace(/\s/g, '')}`}
                                    className="hover:underline"
                                >
                                    {customer.contact_phone}
                                </a>
                            </Detail>
                            <Detail icon={<Mail />} label="Email">
                                <a
                                    href={`mailto:${customer.email}`}
                                    className="hover:underline"
                                >
                                    {customer.email}
                                </a>
                            </Detail>
                            <Detail icon={<MapPin />} label="Address">
                                <AddressLines address={customer.address} />
                            </Detail>
                        </dl>
                        <p className="mt-6 text-xs text-muted-foreground">
                            Customer since {formatDate(customer.created_at)} ·
                            last updated {formatDate(customer.updated_at)}
                        </p>
                    </CardContent>
                </Card>
            )}
        </CustomerShell>
    );
}

CustomerDetails.layout = (
    props: Parameters<typeof customerBreadcrumbs>[0],
) => ({
    breadcrumbs: customerBreadcrumbs(props, [{ title: 'Details', href: '#' }]),
});
