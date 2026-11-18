<?php

namespace App\Support;

use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Server-side error boundary for one section of a page: if loading it fails for any reason, the
 * section shows a message and the rest of the page still renders. That covers more than a daemon
 * being down or refusing a task. A malformed response or a bug in a data class must not 500 the
 * whole page either.
 *
 * Expected outages (queue service down, task refused) are logged as warnings. Anything else is a
 * bug, so it is reported with its trace. HTTP aborts (404, 403), validation and auth exceptions are
 * deliberate, so they are rethrown.
 */
final class Section
{
    /**
     * Run `$load`; on failure log it and return `$unavailable` as the section's error instead.
     *
     * @template T
     *
     * @param  callable(): T  $load
     * @param  array<string, mixed>  $context  for the log entry (ids only, never personal data)
     * @return array{0: T|null, 1: string|null} [value, null] or [null, error message]
     */
    public static function load(callable $load, string $unavailable, string $log, array $context = []): array
    {
        try {
            return [$load(), null];
        } catch (Throwable $e) {
            self::absorb($e, $log, $context);

            return [null, $unavailable];
        }
    }

    /**
     * For hand-written catch blocks: rethrow what must propagate, log or report the rest.
     *
     * @param  array<string, mixed>  $context
     */
    public static function absorb(Throwable $e, string $log, array $context = []): void
    {
        if (! self::recoverable($e)) {
            throw $e;
        }

        if ($e instanceof QueueServiceException || $e instanceof TaskFailedException) {
            Log::warning($log, [...$context, 'error' => $e->getMessage()]);
        } else {
            report($e);
            Log::error($log, [...$context, 'error' => $e::class.': '.$e->getMessage()]);
        }
    }

    public static function recoverable(Throwable $e): bool
    {
        return ! ($e instanceof HttpExceptionInterface
            || $e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException);
    }
}
