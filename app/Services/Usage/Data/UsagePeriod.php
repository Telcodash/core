<?php

namespace App\Services\Usage\Data;

use App\Services\Customers\Data\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use UnexpectedValueException;

/**
 * One billing period of a number's usage, with a row per complete day so far.
 *
 * @phpstan-type Allowances array{minutes: ?int, texts: ?int, data_mb: ?int, eu_roaming: bool, international_roaming: bool}
 * @phpstan-type Day array{date: string, minutes: int, texts: int, data_mb: int, roaming: 'eu'|'world'|null, international_minutes: int}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class UsagePeriod implements Arrayable
{
    /**
     * @param  Allowances|null  $allowances  of the line live at the latest day (null: no line)
     * @param  list<Day>  $days
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public int $daysInPeriod,
        public int $daysElapsed,
        public ?string $tariffName,
        public ?array $allowances,
        public UsageTotals $totals,
        public array $days,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $allowances = null;

        if (($data['allowances'] ?? null) !== null) {
            $a = Payload::object($data, 'allowances');
            $allowances = [
                'minutes' => Payload::nullableInt($a, 'minutes'),
                'texts' => Payload::nullableInt($a, 'texts'),
                'data_mb' => Payload::nullableInt($a, 'data_mb'),
                'eu_roaming' => Payload::bool($a, 'eu_roaming'),
                'international_roaming' => Payload::bool($a, 'international_roaming'),
            ];
        }

        return new self(
            start: Payload::date($data, 'start'),
            end: Payload::date($data, 'end'),
            daysInPeriod: Payload::int($data, 'days_in_period'),
            daysElapsed: Payload::int($data, 'days_elapsed'),
            tariffName: Payload::nullableString($data, 'tariff_name'),
            allowances: $allowances,
            totals: UsageTotals::fromArray(Payload::object($data, 'totals')),
            days: array_map(fn (array $day) => [
                'date' => Payload::string($day, 'date'),
                'minutes' => Payload::int($day, 'minutes'),
                'texts' => Payload::int($day, 'texts'),
                'data_mb' => Payload::int($day, 'data_mb'),
                'roaming' => self::zone(Payload::nullableString($day, 'roaming')),
                'international_minutes' => Payload::int($day, 'international_minutes'),
            ], Payload::list($data, 'days')),
        );
    }

    /**
     * @return 'eu'|'world'|null
     */
    private static function zone(?string $zone): ?string
    {
        return match ($zone) {
            'eu', 'world', null => $zone,
            default => throw new UnexpectedValueException("Unknown roaming zone [{$zone}] in daemon response."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'days_in_period' => $this->daysInPeriod,
            'days_elapsed' => $this->daysElapsed,
            'tariff_name' => $this->tariffName,
            'allowances' => $this->allowances,
            'totals' => $this->totals->toArray(),
            'days' => $this->days,
        ];
    }
}
