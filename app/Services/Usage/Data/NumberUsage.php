<?php

namespace App\Services\Usage\Data;

use App\Services\Customers\Data\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A number's usage per billing period, oldest first; the last period is the current one, so far.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class NumberUsage implements Arrayable
{
    /**
     * @param  list<UsagePeriod>  $periods
     */
    public function __construct(
        public string $msisdn,
        /** Usage covers complete days, up to and including this one. */
        public CarbonImmutable $through,
        /** The tariff the number is on now, or null if it isn't on a line. */
        public ?string $tariffName,
        public array $periods,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $line = ($data['line'] ?? null) === null ? null : Payload::object($data, 'line');

        return new self(
            msisdn: Payload::string($data, 'msisdn'),
            through: Payload::date($data, 'through'),
            tariffName: $line === null ? null : Payload::string($line, 'tariff_name'),
            periods: array_map(UsagePeriod::fromArray(...), Payload::list($data, 'periods')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'msisdn' => $this->msisdn,
            'through' => $this->through->toDateString(),
            'tariff_name' => $this->tariffName,
            'periods' => array_map(fn (UsagePeriod $period) => $period->toArray(), $this->periods),
        ];
    }
}
