import { withErrorBoundary } from '@/components/error-boundary';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ChevronRight,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    AccountStatusDot,
    CustomerStatusBadge,
    formatDate,
    formatMoney,
} from '@/components/customer-status';
import { dashboard } from '@/routes';
import { show } from '@/routes/customers';
import { cn } from '@/lib/utils';

type SearchState = {
    q: string;
    last_name: string;
    postcode: string;
    date_of_birth: string;
    account_number: string;
    msisdn: string;
};

type CustomerSummary = {
    id: string;
    name: string;
    date_of_birth: string;
    status: string;
    address: string;
    postcode: string;
    /** Null when the billing service couldn't be reached. */
    accounts:
        | { account_number: string; status: string; overdue_pence: number }[]
        | null;
};

type Results = {
    data: CustomerSummary[];
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    search: SearchState;
    results: Results | null;
    error: string | null;
    billingUnavailable: boolean;
};

const FILTERS: {
    key: Exclude<keyof SearchState, 'q'>;
    label: string;
    type?: string;
    placeholder?: string;
    inputMode?: 'numeric';
}[] = [
    { key: 'last_name', label: 'Last name', placeholder: 'Smith' },
    { key: 'postcode', label: 'Postcode', placeholder: 'M14 or M14 5RG' },
    { key: 'date_of_birth', label: 'Date of birth', type: 'date' },
    {
        key: 'account_number',
        label: 'Account number',
        placeholder: '10 digits',
        inputMode: 'numeric',
    },
    {
        key: 'msisdn',
        label: 'Mobile number',
        placeholder: '07700 910123',
        type: 'tel',
    },
];

const EXAMPLES = [
    { query: 'jo smi', hint: 'name, partial words match' },
    { query: 'M14', hint: 'postcode or district' },
    { query: '4883209140', hint: 'account number' },
    { query: '07700 910123', hint: 'mobile number' },
    { query: 'name@example.co.uk', hint: 'email' },
];

export default function Dashboard({
    search,
    results,
    error,
    billingUnavailable,
}: Props) {
    const { currentTeam, errors } = usePage().props;
    const [form, setForm] = useState<SearchState>(search);
    const [showFilters, setShowFilters] = useState(
        FILTERS.some(({ key }) => search[key] !== ''),
    );
    const [searching, setSearching] = useState(false);

    // Keep the form in sync when navigating with back/forward.
    useEffect(() => setForm(search), [search]);

    const url = dashboard(currentTeam?.slug ?? '').url;

    const run = (state: SearchState) => {
        const params = Object.fromEntries(
            Object.entries(state).filter(([, value]) => value.trim() !== ''),
        );

        router.get(url, params, {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setSearching(true),
            onFinish: () => setSearching(false),
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        run(form);
    };

    const clear = () => {
        const empty: SearchState = {
            q: '',
            last_name: '',
            postcode: '',
            date_of_birth: '',
            account_number: '',
            msisdn: '',
        };
        setForm(empty);
        router.get(url, {}, { preserveState: true });
    };

    const hasInput = Object.values(form).some((v) => v.trim() !== '');
    const activeFilters = FILTERS.filter(
        ({ key }) => search[key] !== '',
    ).length;

    return (
        <>
            <Head title="Customers" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
                <Heading
                    title="Customers"
                    description="Find a customer by name, postcode, email, mobile number, account number or customer ID"
                />

                <form
                    onSubmit={submit}
                    className="space-y-4"
                    role="search"
                    data-test="customer-search-form"
                >
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="group relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground transition-colors group-focus-within:text-primary" />
                            <Input
                                autoFocus
                                type="search"
                                name="q"
                                value={form.q}
                                onChange={(e) =>
                                    setForm({ ...form, q: e.target.value })
                                }
                                placeholder="Search customers…"
                                className="h-12 rounded-xl pl-10 text-base shadow-sm focus-visible:shadow-[0_8px_24px_-8px_oklch(0.55_0.21_262/0.35)]"
                                aria-label="Search customers"
                                maxLength={100}
                                data-test="customer-search-input"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-12 rounded-xl"
                                onClick={() => setShowFilters((v) => !v)}
                                aria-expanded={showFilters}
                                aria-controls="customer-filters"
                            >
                                <SlidersHorizontal />
                                Filters
                                {activeFilters > 0 && (
                                    <Badge variant="secondary">
                                        {activeFilters}
                                    </Badge>
                                )}
                            </Button>
                            <Button
                                type="submit"
                                className="h-12 flex-1 rounded-xl px-5 sm:flex-none"
                                disabled={!hasInput || searching}
                                data-test="customer-search-submit"
                            >
                                {searching ? <Spinner /> : <Search />}
                                Search
                            </Button>
                        </div>
                    </div>
                    <InputError message={errors.q} />

                    {showFilters && (
                        <div
                            id="customer-filters"
                            className="grid origin-top animate-rise-in gap-4 rounded-xl glass p-4 sm:grid-cols-2 lg:grid-cols-5"
                        >
                            {FILTERS.map((filter) => (
                                <div key={filter.key} className="grid gap-1.5">
                                    <Label htmlFor={`filter-${filter.key}`}>
                                        {filter.label}
                                    </Label>
                                    <Input
                                        id={`filter-${filter.key}`}
                                        name={filter.key}
                                        type={filter.type ?? 'text'}
                                        inputMode={filter.inputMode}
                                        placeholder={filter.placeholder}
                                        value={form[filter.key]}
                                        onChange={(e) =>
                                            setForm({
                                                ...form,
                                                [filter.key]: e.target.value,
                                            })
                                        }
                                    />
                                    <InputError message={errors[filter.key]} />
                                </div>
                            ))}
                            <p className="text-xs text-muted-foreground sm:col-span-2 lg:col-span-5">
                                Filters combine with the search box: every
                                criterion must match.
                            </p>
                        </div>
                    )}
                </form>

                {billingUnavailable && (
                    <Alert data-test="billing-unavailable">
                        <AlertCircle />
                        <AlertTitle>Billing unavailable</AlertTitle>
                        <AlertDescription>
                            Customers are shown, but account numbers and
                            balances can&apos;t be loaded right now.
                        </AlertDescription>
                    </Alert>
                )}

                {error && (
                    <Alert
                        variant="destructive"
                        data-test="customer-search-error"
                    >
                        <AlertCircle />
                        <AlertTitle>Search failed</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {results === null && !error && (
                    <EmptyState
                        onPick={(q) => {
                            const next = { ...form, q };
                            setForm(next);
                            run(next);
                        }}
                    />
                )}

                {results && (
                    <section
                        aria-busy={searching}
                        className={cn(
                            'transition-opacity duration-200',
                            searching && 'opacity-60',
                        )}
                    >
                        <div className="mb-3 flex items-center justify-between gap-4">
                            <p
                                className="text-sm text-muted-foreground"
                                data-test="customer-result-count"
                            >
                                {results.total === 0
                                    ? 'No customers found'
                                    : `Showing ${results.from}–${results.to} of ${results.total.toLocaleString('en-GB')} ${results.total === 1 ? 'customer' : 'customers'}`}
                            </p>
                            <Button variant="ghost" size="sm" onClick={clear}>
                                <X /> Clear search
                            </Button>
                        </div>

                        {results.total === 0 ? (
                            <div className="rounded-xl border border-dashed border-primary/20 bg-glass/50 p-10 text-center">
                                <p className="font-medium">
                                    No matching customers
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Try fewer words, just the postcode district,
                                    or remove a filter.
                                </p>
                            </div>
                        ) : (
                            <ResultsTable
                                customers={results.data}
                                teamSlug={currentTeam?.slug ?? ''}
                            />
                        )}

                        {results.links.length > 3 && (
                            <nav
                                className="mt-4 flex flex-wrap gap-1"
                                aria-label="Pagination"
                            >
                                {results.links.map((link, i) =>
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            preserveState
                                            className={cn(
                                                'rounded-lg border px-3 py-1.5 text-sm transition-colors duration-200',
                                                link.active
                                                    ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                                    : 'bg-glass backdrop-blur-sm hover:border-primary/30 hover:bg-accent hover:text-accent-foreground',
                                            )}
                                            aria-current={
                                                link.active ? 'page' : undefined
                                            }
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : (
                                        <span
                                            key={i}
                                            className="rounded-lg border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ),
                                )}
                            </nav>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

function ResultsTableContent({
    customers,
    teamSlug,
}: {
    customers: CustomerSummary[];
    teamSlug: string;
}) {
    // Route helpers need the team explicitly: URL defaults are only set server-side.
    const customerUrl = (id: string) =>
        show({ current_team: teamSlug, customer: id });

    return (
        <div className="overflow-x-auto rounded-xl glass">
            <table className="w-full text-sm" data-test="customer-results">
                <thead className="border-b bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-2.5 font-medium">Customer</th>
                        <th className="px-4 py-2.5 font-medium">
                            Date of birth
                        </th>
                        <th className="px-4 py-2.5 font-medium">Address</th>
                        <th className="px-4 py-2.5 font-medium">Accounts</th>
                        <th className="w-8 px-2" aria-hidden />
                    </tr>
                </thead>
                <tbody className="stagger divide-y divide-border/60">
                    {customers.map((customer) => (
                        <tr
                            key={customer.id}
                            className="group relative cursor-pointer hover:bg-primary/[0.04]"
                            onClick={() =>
                                router.visit(customerUrl(customer.id))
                            }
                        >
                            <td className="px-4 py-3">
                                <Link
                                    href={customerUrl(customer.id)}
                                    className="font-medium transition-colors group-hover:text-primary hover:underline"
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    {customer.name}
                                </Link>
                                <div className="mt-0.5 flex items-center gap-2">
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {customer.id}
                                    </span>
                                    {customer.status !== 'active' && (
                                        <CustomerStatusBadge
                                            status={customer.status}
                                        />
                                    )}
                                </div>
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap tabular-nums">
                                {formatDate(customer.date_of_birth)}
                            </td>
                            <td className="px-4 py-3">
                                <div>{customer.address}</div>
                                <div className="font-medium">
                                    {customer.postcode}
                                </div>
                            </td>
                            <td className="px-4 py-3">
                                {customer.accounts === null ? (
                                    <span className="text-xs text-muted-foreground">
                                        Unavailable
                                    </span>
                                ) : (
                                    <ul className="space-y-0.5">
                                        {customer.accounts.map((account) => (
                                            <li
                                                key={account.account_number}
                                                className="flex items-center gap-2 font-mono text-xs"
                                            >
                                                <AccountStatusDot
                                                    status={account.status}
                                                />
                                                {account.account_number}
                                                {account.overdue_pence > 0 && (
                                                    <span className="font-sans font-medium text-red-600 dark:text-red-400">
                                                        {formatMoney(
                                                            account.overdue_pence,
                                                        )}{' '}
                                                        overdue
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </td>
                            <td className="px-2 text-muted-foreground">
                                <ChevronRight className="size-4 -translate-x-1 opacity-0 transition-[opacity,transform] duration-200 group-hover:translate-x-0 group-hover:text-primary group-hover:opacity-100" />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function EmptyState({ onPick }: { onPick: (query: string) => void }) {
    return (
        <div className="rounded-xl border border-dashed border-primary/20 bg-glass/50 p-8">
            <p className="font-medium">Search tips</p>
            <p className="mt-1 text-sm text-muted-foreground">
                The search box works out what you typed. Try:
            </p>
            <ul className="mt-4 grid stagger gap-2 sm:grid-cols-2">
                {EXAMPLES.map((example) => (
                    <li key={example.query}>
                        <button
                            type="button"
                            onClick={() => onPick(example.query)}
                            className="group flex w-full items-baseline gap-3 rounded-lg px-2 py-1.5 text-left text-sm transition-colors duration-200 hover:bg-accent"
                        >
                            <code className="rounded-md bg-muted px-1.5 py-0.5 font-mono text-xs transition-colors group-hover:bg-primary/10 group-hover:text-primary">
                                {example.query}
                            </code>
                            <span className="text-muted-foreground">
                                {example.hint}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Customers',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});

const ResultsTable = withErrorBoundary(ResultsTableContent, {
    label: 'the search results',
});
