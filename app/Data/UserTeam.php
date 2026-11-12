<?php

namespace App\Data;

readonly class UserTeam
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $isMember,
        public bool $canManage,
        public bool $canLeave,
        public ?bool $isCurrent = null,
    ) {
        //
    }
}
