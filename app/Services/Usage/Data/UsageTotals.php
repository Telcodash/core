<?php

namespace App\Services\Usage\Data;

use App\Services\Customers\Data\Payload;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Usage added up over a period.
 *
 * @implements Arrayable<string, int>
 */
final readonly class UsageTotals implements Arrayable
{
    public function __construct(
        public int $minutes,
        public int $texts,
        public int $dataMb,
        public int $euRoamingDays,
        public int $worldRoamingDays,
        public int $internationalMinutes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minutes: Payload::int($data, 'minutes'),
            texts: Payload::int($data, 'texts'),
            dataMb: Payload::int($data, 'data_mb'),
            euRoamingDays: Payload::int($data, 'eu_roaming_days'),
            worldRoamingDays: Payload::int($data, 'world_roaming_days'),
            internationalMinutes: Payload::int($data, 'international_minutes'),
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'minutes' => $this->minutes,
            'texts' => $this->texts,
            'data_mb' => $this->dataMb,
            'eu_roaming_days' => $this->euRoamingDays,
            'world_roaming_days' => $this->worldRoamingDays,
            'international_minutes' => $this->internationalMinutes,
        ];
    }
}
