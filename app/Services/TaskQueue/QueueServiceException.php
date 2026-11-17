<?php

namespace App\Services\TaskQueue;

use RuntimeException;
use Throwable;

class QueueServiceException extends RuntimeException
{
    public static function requestFailed(string $action, int $status, string $body): self
    {
        return new self("Queue service {$action} failed with HTTP {$status}: {$body}", $status);
    }

    public static function unreachable(string $action, Throwable $previous): self
    {
        return new self("Queue service unreachable during {$action}: {$previous->getMessage()}", 0, $previous);
    }

    /**
     * Whether retrying the same request could succeed (network error, 5xx, 429).
     */
    public function isTransient(): bool
    {
        return $this->getCode() === 0 || $this->getCode() === 429 || $this->getCode() >= 500;
    }
}
