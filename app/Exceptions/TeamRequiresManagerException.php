<?php

namespace App\Exceptions;

use DomainException;

class TeamRequiresManagerException extends DomainException
{
    public static function forTeam(): self
    {
        return new self('A team must have at least one manager.');
    }

    public static function missingTeam(): self
    {
        return new self('A user must belong to a team.');
    }
}
