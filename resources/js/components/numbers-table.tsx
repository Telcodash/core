import { Form, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Copy,
    MoreHorizontal,
    Pencil,
    Smartphone,
} from 'lucide-react';
import { useState } from 'react';
import { AllowanceList } from '@/components/allowances';
import type { Allowances } from '@/components/allowances';
import { formatDate, formatMoney } from '@/components/customer-status';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuLabel,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { renameNumber, usage } from '@/routes/customers';

/** Longest friendly name the MSISDN Daemon accepts. */
const FRIENDLY_NAME_MAX = 40;

export type NumberRow = {
    msisdn: string;
    national: string;
    classification: 'gold' | 'silver' | 'standard';
    status: 'active' | 'barred';
    barred_reason: string | null;
    /** The customer's own label, e.g. "Dad's phone". */
    friendly_name: string | null;
    assigned_at: string;
    account_id: string;
    account_number: string | null;
    /** null: subscriptions unavailable; false: no line for this number. */
    subscription:
        | {
              id: string;
              tariff_id: string;
              tariff_name: string;
              monthly_price_pence: number;
              allowances: Allowances;
              started_on: string;
          }
        | null
        | false;
};

const CLASSIFICATION_STYLE: Record<string, string> = {
    gold: 'border-amber-500/40 bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    silver: 'border-zinc-400/50 bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
};

/**
 * A number's actions, shared by its right-click menu and its "⋯" button, so both list the same
 * items in the same order. `Item`, `Label` and `Separator` come from whichever menu is rendering.
 */
function NumberActions({
    number,
    customerId,
    onRename,
    Item,
    Label,
    Separator,
}: {
    number: NumberRow;
    customerId: string;
    onRename: () => void;
    Item: typeof ContextMenuItem | typeof DropdownMenuItem;
    Label: typeof ContextMenuLabel | typeof DropdownMenuLabel;
    Separator: typeof ContextMenuSeparator | typeof DropdownMenuSeparator;
}) {
    const { currentTeam } = usePage().props;

    return (
        <>
            <Label className="font-mono">{number.national}</Label>
            <Item asChild>
                <Link
                    href={usage({
                        current_team: currentTeam?.slug ?? '',
                        customer: customerId,
                        msisdn: number.msisdn,
                    })}
                    data-test="number-usage-link"
                >
                    <BarChart3 />
                    Usage
                </Link>
            </Item>
            <Item onSelect={onRename} data-test="number-rename">
                <Pencil />
                {number.friendly_name ? 'Rename' : 'Add friendly name'}
            </Item>
            <Separator />
            <Item
                onSelect={() =>
                    void navigator.clipboard?.writeText(
                        number.national.replace(/\s/g, ''),
                    )
                }
            >
                <Copy />
                Copy number
            </Item>
        </>
    );
}

/** Set, change or clear (by saving it blank) a number's friendly name. */
function RenameDialog({
    number,
    customerId,
    onClose,
}: {
    number: NumberRow | null;
    customerId: string;
    onClose: () => void;
}) {
    const { currentTeam } = usePage().props;

    return (
        <Dialog
            open={number !== null}
            onOpenChange={(next) => !next && onClose()}
        >
            <DialogContent>
                <DialogTitle>Friendly name</DialogTitle>
                <DialogDescription>
                    A label for{' '}
                    <span className="font-mono">{number?.national}</span>, such
                    as who uses it. Leave it blank to remove the name.
                </DialogDescription>

                {number && (
                    <Form
                        {...renameNumber.form.patch({
                            current_team: currentTeam?.slug ?? '',
                            customer: customerId,
                            msisdn: number.msisdn,
                        })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="friendly_name">Name</Label>
                                    <Input
                                        id="friendly_name"
                                        name="friendly_name"
                                        defaultValue={
                                            number.friendly_name ?? ''
                                        }
                                        maxLength={FRIENDLY_NAME_MAX}
                                        placeholder="e.g. Dad's phone"
                                        autoComplete="off"
                                        autoFocus
                                    />
                                    <InputError
                                        message={errors.friendly_name}
                                    />
                                </div>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            type="button"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Save
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

/**
 * The customer's mobile numbers (MSISDNs) with tariff, monthly cost and allowances.
 * Right-click a row, or use its "⋯" button, for the number's actions (Usage first).
 */
export function NumbersTable({
    numbers,
    customerId,
}: {
    numbers: NumberRow[];
    customerId: string;
}) {
    const [renaming, setRenaming] = useState<NumberRow | null>(null);
    const total = numbers.reduce(
        (sum, n) =>
            sum + (n.subscription ? n.subscription.monthly_price_pence : 0),
        0,
    );

    return (
        <>
            <div className="overflow-x-auto rounded-xl glass">
                <table className="w-full text-sm" data-test="numbers-table">
                    <thead className="border-b bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-2.5 font-medium">Number</th>
                            <th className="px-4 py-2.5 font-medium">Account</th>
                            <th className="px-4 py-2.5 font-medium">Tariff</th>
                            <th className="px-4 py-2.5 text-right font-medium">
                                Monthly cost
                            </th>
                            <th className="px-4 py-2.5 font-medium">
                                Allowances
                            </th>
                            <th className="px-4 py-2.5 font-medium">Status</th>
                            <th className="w-0 px-2 py-2.5">
                                <span className="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {numbers.map((n) => (
                            <ContextMenu key={n.msisdn}>
                                <ContextMenuTrigger asChild>
                                    <tr className="align-top data-[state=open]:bg-muted/50">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <Smartphone className="size-4 text-muted-foreground" />
                                                <a
                                                    href={`tel:${n.national.replace(/\s/g, '')}`}
                                                    className="font-mono font-medium whitespace-nowrap tabular-nums hover:underline"
                                                >
                                                    {n.national}
                                                </a>
                                                {n.classification !==
                                                    'standard' && (
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            CLASSIFICATION_STYLE[
                                                                n.classification
                                                            ]
                                                        }
                                                    >
                                                        {n.classification ===
                                                        'gold'
                                                            ? 'Gold'
                                                            : 'Silver'}
                                                    </Badge>
                                                )}
                                            </div>
                                            {n.friendly_name && (
                                                <div
                                                    className="mt-0.5 truncate pl-6 font-medium"
                                                    data-test="number-friendly-name"
                                                >
                                                    {n.friendly_name}
                                                </div>
                                            )}
                                            <div className="mt-0.5 pl-6 text-xs text-muted-foreground">
                                                Since{' '}
                                                {formatDate(
                                                    n.subscription
                                                        ? n.subscription
                                                              .started_on
                                                        : n.assigned_at,
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs whitespace-nowrap">
                                            {n.account_number ?? '—'}
                                        </td>
                                        {n.subscription ? (
                                            <>
                                                <td className="px-4 py-3 font-medium whitespace-nowrap">
                                                    {n.subscription.tariff_name}
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                                                    {formatMoney(
                                                        n.subscription
                                                            .monthly_price_pence,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <AllowanceList
                                                        allowances={
                                                            n.subscription
                                                                .allowances
                                                        }
                                                    />
                                                </td>
                                            </>
                                        ) : (
                                            <td
                                                colSpan={3}
                                                className="px-4 py-3 text-muted-foreground"
                                            >
                                                {n.subscription === null
                                                    ? 'Tariff unavailable right now'
                                                    : 'No line on this number'}
                                            </td>
                                        )}
                                        <td className="px-4 py-3">
                                            {n.status === 'barred' ? (
                                                <Badge
                                                    variant="destructive"
                                                    data-test="line-barred"
                                                >
                                                    Barred
                                                    {n.barred_reason
                                                        ? `: ${n.barred_reason}`
                                                        : ''}
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-600/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                                >
                                                    Active
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-2 py-2">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        aria-label={`Actions for ${n.national}`}
                                                        data-test="number-actions"
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <NumberActions
                                                        number={n}
                                                        customerId={customerId}
                                                        onRename={() =>
                                                            setRenaming(n)
                                                        }
                                                        Item={DropdownMenuItem}
                                                        Label={
                                                            DropdownMenuLabel
                                                        }
                                                        Separator={
                                                            DropdownMenuSeparator
                                                        }
                                                    />
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                </ContextMenuTrigger>
                                <ContextMenuContent>
                                    <NumberActions
                                        number={n}
                                        customerId={customerId}
                                        onRename={() => setRenaming(n)}
                                        Item={ContextMenuItem}
                                        Label={ContextMenuLabel}
                                        Separator={ContextMenuSeparator}
                                    />
                                </ContextMenuContent>
                            </ContextMenu>
                        ))}
                    </tbody>
                    {numbers.some((n) => n.subscription) && (
                        <tfoot className="border-t bg-muted/30 text-sm">
                            <tr>
                                <td
                                    colSpan={3}
                                    className="px-4 py-2.5 text-muted-foreground"
                                >
                                    {numbers.length}{' '}
                                    {numbers.length === 1
                                        ? 'number'
                                        : 'numbers'}
                                </td>
                                <td className="px-4 py-2.5 text-right font-semibold tabular-nums">
                                    {formatMoney(total)}
                                </td>
                                <td
                                    colSpan={3}
                                    className="px-4 py-2.5 text-muted-foreground"
                                >
                                    per month
                                </td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
            <RenameDialog
                key={renaming?.msisdn ?? 'closed'}
                number={renaming}
                customerId={customerId}
                onClose={() => setRenaming(null)}
            />
        </>
    );
}
