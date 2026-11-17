<?php

namespace App\Services\Billing\Data;

use App\Services\Customers\Data\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use UnexpectedValueException;

/**
 * An invoice with its payment attempts (oldest attempt first).
 *
 * Each line item has a `kind`: `plan` (a line's monthly price for this period, in advance),
 * `prorata` (part of the previous period for a line that started during it) or `extra`
 * (out-of-allowance usage in the previous period, in arrears), plus the `msisdn` it's for.
 * Invoices from before per-line billing have neither (null).
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Invoice implements Arrayable
{
    /**
     * @param  list<array{description: string, amount_pence: int, kind: 'plan'|'prorata'|'extra'|null, msisdn: string|null}>  $lineItems
     * @param  list<Payment>  $payments
     */
    public function __construct(
        public string $id,
        public string $invoiceNumber,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
        public CarbonImmutable $issuedOn,
        public CarbonImmutable $dueOn,
        public array $lineItems,
        public int $amountPence,
        /** open | paid | overdue */
        public string $status,
        public ?CarbonImmutable $paidOn,
        public array $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            invoiceNumber: Payload::string($data, 'invoice_number'),
            periodStart: Payload::date($data, 'period_start'),
            periodEnd: Payload::date($data, 'period_end'),
            issuedOn: Payload::date($data, 'issued_on'),
            dueOn: Payload::date($data, 'due_on'),
            lineItems: array_map(fn (array $item) => [
                'description' => Payload::string($item, 'description'),
                'amount_pence' => Payload::int($item, 'amount_pence'),
                'kind' => self::kind(Payload::nullableString($item, 'kind')),
                'msisdn' => Payload::nullableString($item, 'msisdn'),
            ], Payload::list($data, 'line_items')),
            amountPence: Payload::int($data, 'amount_pence'),
            status: Payload::string($data, 'status'),
            paidOn: Payload::nullableDate($data, 'paid_on'),
            payments: array_map(Payment::fromArray(...), Payload::list($data, 'payments')),
        );
    }

    /**
     * @return 'plan'|'prorata'|'extra'|null
     */
    private static function kind(?string $kind): ?string
    {
        return match ($kind) {
            'plan', 'prorata', 'extra', null => $kind,
            default => throw new UnexpectedValueException("Unknown invoice item kind [{$kind}] in daemon response."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoiceNumber,
            'period_start' => $this->periodStart->toDateString(),
            'period_end' => $this->periodEnd->toDateString(),
            'issued_on' => $this->issuedOn->toDateString(),
            'due_on' => $this->dueOn->toDateString(),
            'line_items' => $this->lineItems,
            'amount_pence' => $this->amountPence,
            'status' => $this->status,
            'paid_on' => $this->paidOn?->toDateString(),
            'payments' => array_map(fn (Payment $payment) => $payment->toArray(), $this->payments),
        ];
    }
}
