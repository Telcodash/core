import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Check, Copy } from 'lucide-react';
import type { ReactNode } from 'react';
import { CustomerStatusBadge, formatDate } from '@/components/customer-status';
import { PageTransition } from '@/components/page-transition';
import { isEmbeddedPane } from '@/components/side-pane';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { billing, details, numbers, show } from '@/routes/customers';
import type { BreadcrumbItem } from '@/types';

/** What every customer view's header needs. Fields are null when the customer daemon couldn't say. */
export type CustomerHeader = {
    id: string;
    full_name: string | null;
    status: string | null;
    created_at: string | null;
};

type Section = 'overview' | 'details' | 'numbers' | 'billing';

const SECTIONS: { key: Section; title: string; route: typeof show }[] = [
    { key: 'overview', title: 'Overview', route: show },
    { key: 'details', title: 'Details', route: details },
    { key: 'numbers', title: 'Numbers', route: numbers },
    { key: 'billing', title: 'Billing', route: billing },
];

export function CopyButton({ value, label }: { value: string; label: string }) {
    const [copied, copy] = useClipboard();

    return (
        <Button
            variant="ghost"
            size="icon"
            className="size-6"
            onClick={() => void copy(value)}
            aria-label={`Copy ${label}`}
            title={`Copy ${label}`}
        >
            {copied === value ? (
                <Check className="size-3.5 text-emerald-600" />
            ) : (
                <Copy className="size-3.5" />
            )}
        </Button>
    );
}

/** A labelled value with an icon, e.g. a customer's phone number. */
export function Detail({
    icon,
    label,
    children,
}: {
    icon: ReactNode;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex gap-3">
            <span className="mt-0.5 text-muted-foreground [&>svg]:size-4">
                {icon}
            </span>
            <div className="min-w-0">
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="mt-0.5 break-words">{children}</dd>
            </div>
        </div>
    );
}

/** URL of one of a customer's views, for the current team. */
export function useCustomerUrl() {
    const { currentTeam } = usePage().props;

    return (section: Section, customerId: string) =>
        SECTIONS.find((s) => s.key === section)!.route({
            current_team: currentTeam?.slug ?? '',
            customer: customerId,
        });
}

function BackToSearch() {
    const { currentTeam } = usePage().props;

    return (
        <button
            type="button"
            onClick={() =>
                window.history.length > 1
                    ? window.history.back()
                    : router.visit(dashboard(currentTeam?.slug ?? ''))
            }
            className="group inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            <ArrowLeft className="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />{' '}
            Back
        </button>
    );
}

/** Sub-navigation between a customer's views. Numbers stays active on a number's Usage page. */
function CustomerNav({ customerId }: { customerId: string }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const url = useCustomerUrl();

    return (
        // No prefetching: opening a view fetches personal data and is audited, so only real visits should.
        // The rule is an inset shadow and the indicator sits inside the nav: nothing overhangs its
        // box, so it's exactly its tabs' height and only scrolls sideways (bar hidden) on phones.
        <nav
            aria-label="Customer"
            className="-mx-1 flex [scrollbar-width:none] gap-1 overflow-x-auto overflow-y-hidden shadow-[inset_0_-1px_0_var(--color-border)] [&::-webkit-scrollbar]:hidden"
            data-test="customer-nav"
        >
            {SECTIONS.map(({ key, title }) => {
                const href = url(key, customerId);
                // Overview's URL is the parent of the others, so it's only active on itself.
                const active =
                    key === 'overview'
                        ? isCurrentUrl(href)
                        : isCurrentOrParentUrl(href);

                return (
                    <Link
                        key={key}
                        href={href}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            "relative shrink-0 px-3 py-2.5 text-sm whitespace-nowrap transition-colors duration-200 after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 after:origin-center after:rounded-full after:bg-primary after:transition-transform after:duration-300 after:ease-(--ease-out-expo) after:content-['']",
                            active
                                ? 'font-medium text-foreground after:scale-x-100'
                                : 'text-muted-foreground after:scale-x-0 hover:text-foreground hover:after:scale-x-50 hover:after:bg-primary/40',
                        )}
                    >
                        {title}
                    </Link>
                );
            })}
        </nav>
    );
}

/**
 * The frame every customer view shares: header (name, status, ID), sub-navigation, then the view.
 * With no customer (the customer daemon is down) it shows `error` instead of the view.
 */
export function CustomerShell({
    customerId,
    customer,
    title,
    error,
    className,
    children,
}: {
    customerId: string;
    customer: CustomerHeader | null;
    /** Page title suffix, e.g. "Billing". */
    title: string;
    error: string | null;
    className?: string;
    children: ReactNode;
}) {
    const name = customer?.full_name ?? 'Customer';
    const content =
        customer === null ? (
            <Alert variant="destructive">
                <AlertCircle />
                <AlertTitle>Couldn&apos;t load this customer</AlertTitle>
                <AlertDescription>{error}</AlertDescription>
            </Alert>
        ) : (
            children
        );

    // In a side pane, just the view: the page beside it already shows who this is (the pane's
    // title bar names the customer and view, from the page title).
    if (isEmbeddedPane) {
        return (
            <>
                <Head title={`${name} · ${title}`} />
                <div
                    className={cn(
                        'mx-auto w-full max-w-6xl p-4 md:p-6',
                        className,
                    )}
                >
                    {/* The spacing goes on the transition's wrapper: it holds the view's sections. */}
                    <PageTransition
                        className="block space-y-6"
                        errorLabel={`this customer’s ${title.toLowerCase()}`}
                    >
                        {content}
                    </PageTransition>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`${name} · ${title}`} />

            <div
                className={cn(
                    'mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6',
                    className,
                )}
            >
                <BackToSearch />

                <div className="space-y-4">
                    <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div className="flex flex-wrap items-center gap-3">
                                <h1
                                    className="text-2xl font-semibold tracking-tight"
                                    data-test="customer-name"
                                >
                                    {name}
                                </h1>
                                {customer?.status && (
                                    <CustomerStatusBadge
                                        status={customer.status}
                                    />
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-1 font-mono text-xs text-muted-foreground">
                                {customerId}
                                <CopyButton
                                    value={customerId}
                                    label="customer ID"
                                />
                            </div>
                        </div>
                        {customer?.created_at && (
                            <p className="text-xs text-muted-foreground sm:text-right">
                                Customer since{' '}
                                <span className="text-foreground">
                                    {formatDate(customer.created_at)}
                                </span>
                            </p>
                        )}
                    </header>

                    <CustomerNav customerId={customerId} />
                </div>

                {/* Only the view transitions between a customer's views; the header stays put. */}
                <PageTransition
                    className="block space-y-6"
                    errorLabel={`this customer’s ${title.toLowerCase()}`}
                >
                    {content}
                </PageTransition>
            </div>
        </>
    );
}

/** Breadcrumbs for a customer view: Customers › name › (view) › (extra). */
export function customerBreadcrumbs(
    props: {
        currentTeam?: { slug: string } | null;
        customer?: { full_name: string | null } | null;
        customerId?: string;
    },
    trail: BreadcrumbItem[] = [],
): BreadcrumbItem[] {
    const team = props.currentTeam?.slug;

    return [
        { title: 'Customers', href: team ? dashboard(team) : '/' },
        {
            title: props.customer?.full_name ?? 'Customer',
            href:
                team && props.customerId
                    ? show({ current_team: team, customer: props.customerId })
                    : '#',
        },
        ...trail,
    ];
}
