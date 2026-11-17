<?php

namespace App\Services\Subscriptions\Data;

use App\Services\Customers\Data\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A line: a number on a tariff, billed to an account, at the price locked in when it started.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SubscriptionLine implements Arrayable
{
    public function __construct(
        public string $id,
        public string $msisdn,
        public string $accountId,
        public string $tariffId,
        public string $tariffName,
        public int $monthlyPricePence,
        public Allowances $allowances,
        public CarbonImmutable $startedOn,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            msisdn: Payload::string($data, 'msisdn'),
            accountId: Payload::string($data, 'account_id'),
            tariffId: Payload::string($data, 'tariff_id'),
            tariffName: Payload::string($data, 'tariff_name'),
            monthlyPricePence: Payload::int($data, 'monthly_price_pence'),
            allowances: Allowances::fromArray($data),
            startedOn: Payload::date($data, 'started_on'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tariff_id' => $this->tariffId,
            'tariff_name' => $this->tariffName,
            'monthly_price_pence' => $this->monthlyPricePence,
            'allowances' => $this->allowances->toArray(),
            'started_on' => $this->startedOn->toDateString(),
        ];
    }
}
