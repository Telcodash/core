<?php

namespace App\Services\Billing\Data;

use App\Services\Customers\Data\Payload;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Masked payment details: the billing daemon never holds (or returns) full card or bank numbers.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PaymentMethod implements Arrayable
{
    public function __construct(
        /** direct_debit | card */
        public string $type,
        /** e.g. "Direct Debit ****1234" or "Visa ****4242" */
        public string $label,
        public ?string $sortCode,
        public ?string $mandateReference,
        public ?string $brand,
        public ?string $expiry,
        public bool $expired,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: Payload::string($data, 'type'),
            label: Payload::string($data, 'label'),
            sortCode: Payload::nullableString($data, 'sort_code'),
            mandateReference: Payload::nullableString($data, 'mandate_reference'),
            brand: Payload::nullableString($data, 'brand'),
            expiry: Payload::nullableString($data, 'expiry'),
            expired: ($data['expired'] ?? false) === true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'sort_code' => $this->sortCode,
            'mandate_reference' => $this->mandateReference,
            'brand' => $this->brand,
            'expiry' => $this->expiry,
            'expired' => $this->expired,
        ];
    }
}
