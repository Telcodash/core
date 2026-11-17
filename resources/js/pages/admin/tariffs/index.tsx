import { Form, Head, usePage } from '@inertiajs/react';
import { AlertCircle, Lock, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import TariffController from '@/actions/App/Http/Controllers/Admin/TariffController';
import { AllowanceList } from '@/components/allowances';
import type { Allowances } from '@/components/allowances';
import { formatMoney } from '@/components/customer-status';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/tariffs';

type Tariff = {
    id: string;
    name: string;
    description: string | null;
    monthly_price_pence: number;
    allowances: Allowances;
    available: boolean;
    active_lines: number;
    /** Price and allowances are frozen while customers are on it. */
    locked: boolean;
};

type Props = {
    tariffs: Tariff[];
    error: string | null;
};

export default function Tariffs({ tariffs, error }: Props) {
    const [editing, setEditing] = useState<Tariff | 'new' | null>(null);
    const { errors } = usePage().props;

    return (
        <>
            <Head title="Tariffs" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Tariffs"
                        description="The plans lines can be sold on. Customers keep the price they signed up at."
                    />
                    <Button
                        onClick={() => setEditing('new')}
                        disabled={error !== null}
                        data-test="new-tariff-button"
                    >
                        <Plus /> New tariff
                    </Button>
                </div>

                {(error || (errors.tariff && editing === null)) && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>Tariff catalogue</AlertTitle>
                        <AlertDescription>
                            {error ?? errors.tariff}
                        </AlertDescription>
                    </Alert>
                )}

                {tariffs.length > 0 && (
                    <div className="overflow-x-auto rounded-xl border">
                        <table
                            className="w-full text-sm"
                            data-test="tariffs-table"
                        >
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2.5 font-medium">
                                        Tariff
                                    </th>
                                    <th className="px-4 py-2.5 text-right font-medium">
                                        Monthly price
                                    </th>
                                    <th className="px-4 py-2.5 font-medium">
                                        Allowances
                                    </th>
                                    <th className="px-4 py-2.5 text-right font-medium">
                                        Active lines
                                    </th>
                                    <th className="px-4 py-2.5 font-medium">
                                        Status
                                    </th>
                                    <th className="w-10 px-2" aria-hidden />
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {tariffs.map((tariff) => (
                                    <tr
                                        key={tariff.id}
                                        className={`align-top ${tariff.available ? '' : 'bg-muted/20'}`}
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {tariff.name}
                                            </div>
                                            {tariff.description && (
                                                <div className="mt-0.5 max-w-xs text-xs text-muted-foreground">
                                                    {tariff.description}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium tabular-nums">
                                            {formatMoney(
                                                tariff.monthly_price_pence,
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <AllowanceList
                                                allowances={tariff.allowances}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {tariff.active_lines.toLocaleString(
                                                'en-GB',
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {tariff.available ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-600/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                                >
                                                    On sale
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Retired
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-2 py-2">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setEditing(tariff)
                                                }
                                                aria-label={`Edit ${tariff.name}`}
                                                data-test="edit-tariff-button"
                                            >
                                                <Pencil />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <TariffDialog
                key={
                    editing === null
                        ? 'closed'
                        : editing === 'new'
                          ? 'new'
                          : editing.id
                }
                tariff={editing === 'new' ? null : editing}
                open={editing !== null}
                onClose={() => setEditing(null)}
            />
        </>
    );
}

/** A boolean field: a hidden "0" so unticking is submitted, then the checkbox's "1". */
function Flag({
    name,
    label,
    defaultChecked,
    hint,
}: {
    name: string;
    label: string;
    defaultChecked: boolean;
    hint?: string;
}) {
    return (
        <label className="flex items-start gap-2 text-sm">
            <input type="hidden" name={name} value="0" />
            <Checkbox
                name={name}
                value="1"
                defaultChecked={defaultChecked}
                className="mt-0.5"
            />
            <span>
                {label}
                {hint && (
                    <span className="block text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </span>
        </label>
    );
}

/** A quantity that can be unlimited (null). */
function Quantity({
    name,
    unlimitedName,
    label,
    unit,
    step,
    initial,
    error,
}: {
    name: string;
    unlimitedName: string;
    label: string;
    unit: string;
    step?: string;
    initial: number | null | undefined;
    error?: string;
}) {
    const [unlimited, setUnlimited] = useState(initial === null);

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={`tariff-${name}`}>{label}</Label>
            <div className="flex items-center gap-2">
                <Input
                    id={`tariff-${name}`}
                    name={name}
                    type="number"
                    min="0"
                    step={step ?? '1'}
                    defaultValue={initial ?? ''}
                    disabled={unlimited}
                    className="w-28"
                />
                <span className="text-sm text-muted-foreground">{unit}</span>
                <label className="ml-auto flex items-center gap-1.5 text-sm">
                    <input type="hidden" name={unlimitedName} value="0" />
                    <Checkbox
                        name={unlimitedName}
                        value="1"
                        checked={unlimited}
                        onCheckedChange={(v) => setUnlimited(v === true)}
                    />
                    Unlimited
                </label>
            </div>
            <InputError message={error} />
        </div>
    );
}

function TariffDialog({
    tariff,
    open,
    onClose,
}: {
    tariff: Tariff | null;
    open: boolean;
    onClose: () => void;
}) {
    const locked = tariff?.locked ?? false;
    const action = tariff
        ? TariffController.update.form(tariff.id)
        : TariffController.store.form();
    const a = tariff?.allowances;

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>
                    {tariff ? `Edit ${tariff.name}` : 'New tariff'}
                </DialogTitle>
                <DialogDescription>
                    {tariff
                        ? 'Changes apply to the catalogue immediately.'
                        : 'New tariffs are on sale as soon as they are created.'}
                </DialogDescription>

                <Form
                    {...action}
                    onSuccess={onClose}
                    className="space-y-4"
                    data-test="tariff-form"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.tariff} />

                            <div className="grid gap-1.5">
                                <Label htmlFor="tariff-name">Name</Label>
                                <Input
                                    id="tariff-name"
                                    name="name"
                                    defaultValue={tariff?.name}
                                    required
                                    maxLength={60}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="tariff-description">
                                    Description
                                </Label>
                                <Input
                                    id="tariff-description"
                                    name="description"
                                    defaultValue={tariff?.description ?? ''}
                                    maxLength={200}
                                    placeholder="Optional"
                                />
                                <InputError message={errors.description} />
                            </div>

                            {locked && tariff ? (
                                <div
                                    className="space-y-3 rounded-lg border bg-muted/30 p-3"
                                    data-test="tariff-locked"
                                >
                                    <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                        <Lock className="mt-0.5 size-4 shrink-0" />
                                        {tariff.active_lines.toLocaleString(
                                            'en-GB',
                                        )}{' '}
                                        customer line(s) are on this tariff, so
                                        its price and allowances can&apos;t
                                        change. To change the plan, create a new
                                        tariff and retire this one.
                                    </p>
                                    <div className="text-sm font-medium">
                                        {formatMoney(
                                            tariff.monthly_price_pence,
                                        )}{' '}
                                        / month
                                    </div>
                                    <AllowanceList
                                        allowances={tariff.allowances}
                                    />
                                </div>
                            ) : (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="tariff-price">
                                            Monthly price
                                        </Label>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-muted-foreground">
                                                £
                                            </span>
                                            <Input
                                                id="tariff-price"
                                                name="monthly_price"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                defaultValue={
                                                    tariff
                                                        ? (
                                                              tariff.monthly_price_pence /
                                                              100
                                                          ).toFixed(2)
                                                        : ''
                                                }
                                                required
                                                className="w-28"
                                            />
                                        </div>
                                        <InputError
                                            message={errors.monthly_price}
                                        />
                                    </div>

                                    <Quantity
                                        name="minutes"
                                        unlimitedName="minutes_unlimited"
                                        label="Calls"
                                        unit="minutes"
                                        initial={
                                            tariff ? a!.minutes : undefined
                                        }
                                        error={errors.minutes}
                                    />
                                    <Quantity
                                        name="texts"
                                        unlimitedName="texts_unlimited"
                                        label="Texts"
                                        unit="texts"
                                        initial={tariff ? a!.texts : undefined}
                                        error={errors.texts}
                                    />
                                    <Quantity
                                        name="data_gb"
                                        unlimitedName="data_unlimited"
                                        label="Data"
                                        unit="GB"
                                        step="0.5"
                                        initial={
                                            tariff
                                                ? a!.data_mb === null
                                                    ? null
                                                    : a!.data_mb / 1024
                                                : undefined
                                        }
                                        error={errors.data_gb}
                                    />

                                    <div className="grid gap-2 sm:grid-cols-3">
                                        <Flag
                                            name="eu_roaming"
                                            label="EU roaming"
                                            defaultChecked={
                                                a?.eu_roaming ?? true
                                            }
                                        />
                                        <Flag
                                            name="international_roaming"
                                            label="International roaming"
                                            defaultChecked={
                                                a?.international_roaming ??
                                                false
                                            }
                                        />
                                        <Flag
                                            name="five_g_plus"
                                            label="5G+ enabled"
                                            defaultChecked={
                                                a?.five_g_plus ?? false
                                            }
                                        />
                                    </div>
                                </>
                            )}

                            <div className="border-t pt-3">
                                <Flag
                                    name="available"
                                    label="On sale"
                                    defaultChecked={tariff?.available ?? true}
                                    hint="Untick to retire: existing lines keep it, but it can't be sold."
                                />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="save-tariff-button"
                                >
                                    {processing && <Spinner />}
                                    {tariff ? 'Save changes' : 'Create tariff'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Tariffs.layout = {
    breadcrumbs: [
        { title: 'Administration', href: index.url() },
        { title: 'Tariffs', href: index.url() },
    ],
};
