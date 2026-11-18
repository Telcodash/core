<?php

namespace App\Services\Numbers\Data;

use App\Services\Customers\Data\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A mobile number live on a billing account (from the MSISDN Daemon).
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Line implements Arrayable
{
    public function __construct(
        /** E.164 digits, e.g. 447700910123 */
        public string $msisdn,
        /** e.g. 07700 910123 */
        public string $national,
        /** gold | silver | standard */
        public string $classification,
        /** active | barred */
        public string $status,
        public ?string $barredReason,
        /** The customer's own label for the number, e.g. "Dad's phone" */
        public ?string $friendlyName,
        public CarbonImmutable $assignedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            msisdn: Payload::string($data, 'msisdn'),
            national: Payload::string($data, 'national'),
            classification: Payload::string($data, 'classification'),
            status: Payload::string($data, 'status'),
            barredReason: Payload::nullableString($data, 'barred_reason'),
            friendlyName: Payload::nullableString($data, 'friendly_name'),
            assignedAt: Payload::date($data, 'assigned_at'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'msisdn' => $this->msisdn,
            'national' => $this->national,
            'classification' => $this->classification,
            'status' => $this->status,
            'barred_reason' => $this->barredReason,
            'friendly_name' => $this->friendlyName,
            'assigned_at' => $this->assignedAt->toIso8601String(),
        ];
    }
}
