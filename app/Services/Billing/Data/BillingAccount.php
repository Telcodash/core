<?php

namespace App\Services\Billing\Data;

use App\Services\Customers\Data\Address;
use App\Services\Customers\Data\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A billing account as held by the Billing Daemon. Money is integer pence (GBP).
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class BillingAccount implements Arrayable
{
    public function __construct(
        public string $id,
        /** Reference into the Customer Account Daemon (cus_…). */
        public string $customerId,
        public string $accountNumber,
        /** active | suspended | closed */
        public string $status,
        /** null | arrears */
        public ?string $suspensionReason,
        public int $monthlyChargePence,
        public int $billingDay,
        public ?CarbonImmutable $nextBillOn,
        /** Unpaid (open + overdue) invoices. */
        public int $balancePence,
        public int $overduePence,
        public PaymentMethod $paymentMethod,
        /** Null means bills go to the customer's address. */
        public ?Address $billingAddress,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $closedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            customerId: Payload::string($data, 'customer_id'),
            accountNumber: Payload::string($data, 'account_number'),
            status: Payload::string($data, 'status'),
            suspensionReason: Payload::nullableString($data, 'suspension_reason'),
            monthlyChargePence: Payload::int($data, 'monthly_charge_pence'),
            billingDay: Payload::int($data, 'billing_day'),
            nextBillOn: Payload::nullableDate($data, 'next_bill_on'),
            balancePence: Payload::int($data, 'balance_pence'),
            overduePence: Payload::int($data, 'overdue_pence'),
            paymentMethod: PaymentMethod::fromArray(Payload::object($data, 'payment_method')),
            billingAddress: ($data['billing_address'] ?? null) === null
                ? null
                : Address::fromArray(Payload::object($data, 'billing_address')),
            createdAt: Payload::date($data, 'created_at'),
            closedAt: Payload::nullableDate($data, 'closed_at'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customerId,
            'account_number' => $this->accountNumber,
            'status' => $this->status,
            'suspension_reason' => $this->suspensionReason,
            'monthly_charge_pence' => $this->monthlyChargePence,
            'billing_day' => $this->billingDay,
            'next_bill_on' => $this->nextBillOn?->toDateString(),
            'balance_pence' => $this->balancePence,
            'overdue_pence' => $this->overduePence,
            'payment_method' => $this->paymentMethod->toArray(),
            'billing_address' => $this->billingAddress?->toArray(),
            'created_at' => $this->createdAt->toIso8601String(),
            'closed_at' => $this->closedAt?->toIso8601String(),
        ];
    }
}
