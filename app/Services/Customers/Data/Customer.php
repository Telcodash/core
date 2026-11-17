<?php

namespace App\Services\Customers\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A customer as held by the Customer Account Daemon. Not persisted in Core.
 * Billing accounts are a separate concern: see App\Services\Billing\BillingDirectory.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Customer implements Arrayable
{
    public function __construct(
        public string $id,
        public ?string $title,
        public string $firstName,
        public string $lastName,
        public CarbonImmutable $dateOfBirth,
        public string $email,
        public string $contactPhone,
        public Address $address,
        /** active | closed */
        public string $status,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            title: Payload::nullableString($data, 'title'),
            firstName: Payload::string($data, 'first_name'),
            lastName: Payload::string($data, 'last_name'),
            dateOfBirth: Payload::date($data, 'date_of_birth')->startOfDay(),
            email: Payload::string($data, 'email'),
            contactPhone: Payload::string($data, 'contact_phone'),
            address: Address::fromArray(Payload::object($data, 'address')),
            status: Payload::string($data, 'status'),
            createdAt: Payload::date($data, 'created_at'),
            updatedAt: Payload::date($data, 'updated_at'),
        );
    }

    public function fullName(): string
    {
        return trim(($this->title ? "{$this->title} " : '')."{$this->firstName} {$this->lastName}");
    }

    public function age(): int
    {
        return (int) $this->dateOfBirth->diffInYears(now());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName(),
            'date_of_birth' => $this->dateOfBirth->toDateString(),
            'email' => $this->email,
            'contact_phone' => $this->contactPhone,
            'address' => $this->address->toArray(),
            'status' => $this->status,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
