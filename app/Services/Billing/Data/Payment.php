<?php

namespace App\Services\Billing\Data;

use App\Services\Customers\Data\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One attempt to collect an invoice.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Payment implements Arrayable
{
    public function __construct(
        public string $id,
        public int $attempt,
        /** scheduled | customer */
        public string $initiatedBy,
        /** direct_debit | card */
        public string $method,
        public int $amountPence,
        /** succeeded | failed */
        public string $status,
        public ?string $failureCode,
        public ?string $failureMessage,
        public string $processorReference,
        public CarbonImmutable $attemptedOn,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            attempt: Payload::int($data, 'attempt'),
            initiatedBy: Payload::string($data, 'initiated_by'),
            method: Payload::string($data, 'method'),
            amountPence: Payload::int($data, 'amount_pence'),
            status: Payload::string($data, 'status'),
            failureCode: Payload::nullableString($data, 'failure_code'),
            failureMessage: Payload::nullableString($data, 'failure_message'),
            processorReference: Payload::string($data, 'processor_reference'),
            attemptedOn: Payload::date($data, 'attempted_on'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'attempt' => $this->attempt,
            'initiated_by' => $this->initiatedBy,
            'method' => $this->method,
            'amount_pence' => $this->amountPence,
            'status' => $this->status,
            'failure_code' => $this->failureCode,
            'failure_message' => $this->failureMessage,
            'processor_reference' => $this->processorReference,
            'attempted_on' => $this->attemptedOn->toDateString(),
        ];
    }
}
