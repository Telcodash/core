<?php

namespace App\Services\Billing\Data;

use App\Services\Customers\Data\Payload;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The slice of a billing account shown in search results.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AccountSummary implements Arrayable
{
    public function __construct(
        public string $id,
        public string $accountNumber,
        public string $status,
        public int $balancePence,
        public int $overduePence,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            accountNumber: Payload::string($data, 'account_number'),
            status: Payload::string($data, 'status'),
            balancePence: Payload::int($data, 'balance_pence'),
            overduePence: Payload::int($data, 'overdue_pence'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->accountNumber,
            'status' => $this->status,
            'balance_pence' => $this->balancePence,
            'overdue_pence' => $this->overduePence,
        ];
    }
}
