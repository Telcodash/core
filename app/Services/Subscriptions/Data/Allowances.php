<?php

namespace App\Services\Subscriptions\Data;

use App\Services\Customers\Data\Payload;
use Illuminate\Contracts\Support\Arrayable;

/**
 * What a tariff includes each month. Null quantities mean unlimited.
 *
 * @implements Arrayable<string, int|bool|null>
 */
final readonly class Allowances implements Arrayable
{
    public function __construct(
        public ?int $minutes,
        public ?int $texts,
        public ?int $dataMb,
        public bool $euRoaming,
        public bool $internationalRoaming,
        public bool $fiveGPlus,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minutes: Payload::nullableInt($data, 'minutes'),
            texts: Payload::nullableInt($data, 'texts'),
            dataMb: Payload::nullableInt($data, 'data_mb'),
            euRoaming: Payload::bool($data, 'eu_roaming'),
            internationalRoaming: Payload::bool($data, 'international_roaming'),
            fiveGPlus: Payload::bool($data, 'five_g_plus'),
        );
    }

    /**
     * @return array<string, int|bool|null>
     */
    public function toArray(): array
    {
        return [
            'minutes' => $this->minutes,
            'texts' => $this->texts,
            'data_mb' => $this->dataMb,
            'eu_roaming' => $this->euRoaming,
            'international_roaming' => $this->internationalRoaming,
            'five_g_plus' => $this->fiveGPlus,
        ];
    }
}
