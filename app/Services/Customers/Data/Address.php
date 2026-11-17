<?php

namespace App\Services\Customers\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A UK postal address in Royal Mail line order.
 *
 * @implements Arrayable<string, string|null>
 */
final readonly class Address implements Arrayable
{
    public function __construct(
        public string $line1,
        public ?string $line2,
        public string $town,
        public ?string $county,
        public string $postcode,
        public string $country = 'GB',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            line1: Payload::string($data, 'line_1'),
            line2: Payload::nullableString($data, 'line_2'),
            town: Payload::string($data, 'town'),
            county: Payload::nullableString($data, 'county'),
            postcode: Payload::string($data, 'postcode'),
            country: Payload::nullableString($data, 'country') ?? 'GB',
        );
    }

    /**
     * Address lines for display, with the post town uppercased as Royal Mail prints it.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_values(array_filter([
            $this->line1,
            $this->line2,
            mb_strtoupper($this->town),
            $this->county,
            $this->postcode,
        ]));
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'line_1' => $this->line1,
            'line_2' => $this->line2,
            'town' => $this->town,
            'county' => $this->county,
            'postcode' => $this->postcode,
            'country' => $this->country,
        ];
    }
}
