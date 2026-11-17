<?php

namespace App\Services\Subscriptions\Data;

use App\Services\Customers\Data\Payload;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A plan in the Subscription Daemon's catalogue.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Tariff implements Arrayable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public int $monthlyPricePence,
        public Allowances $allowances,
        /** False once retired: existing lines keep it, but it can't be sold. */
        public bool $available,
        public int $activeLines,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            name: Payload::string($data, 'name'),
            description: Payload::nullableString($data, 'description'),
            monthlyPricePence: Payload::int($data, 'monthly_price_pence'),
            allowances: Allowances::fromArray($data),
            available: Payload::bool($data, 'available'),
            activeLines: Payload::int($data, 'active_lines'),
        );
    }

    /**
     * Price and allowances can't change while customers are on the tariff.
     */
    public function isLocked(): bool
    {
        return $this->activeLines > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'monthly_price_pence' => $this->monthlyPricePence,
            'allowances' => $this->allowances->toArray(),
            'available' => $this->available,
            'active_lines' => $this->activeLines,
            'locked' => $this->isLocked(),
        ];
    }
}
