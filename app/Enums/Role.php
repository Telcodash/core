<?php

namespace App\Enums;

enum Role: string
{
    case Advisor = 'advisor';
    case AdvisorPlus = 'advisor_plus';
    case Manager = 'manager';
    case Administrator = 'administrator';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Advisor => 'Advisor',
            self::AdvisorPlus => 'Advisor Plus',
            self::Manager => 'Manager',
            self::Administrator => 'Administrator',
        };
    }

    /**
     * Get the hierarchy level for this role. Higher numbers are more privileged.
     */
    public function level(): int
    {
        return match ($this) {
            self::Advisor => 1,
            self::AdvisorPlus => 2,
            self::Manager => 3,
            self::Administrator => 4,
        };
    }

    /**
     * Determine if this role is at least as privileged as the given role.
     */
    public function isAtLeast(self $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Determine if this role can manage a team (manager or above).
     */
    public function isManagerOrAbove(): bool
    {
        return $this->isAtLeast(self::Manager);
    }

    /**
     * Get the role values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
