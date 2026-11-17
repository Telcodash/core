<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify `X-Queue-Signature: t=<unix>,v1=<hex>` where v1 = HMAC-SHA256(secret, "<t>.<raw body>").
 */
class VerifyQueueSignature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.queue.webhook_secret');

        abort_if(! is_string($secret) || $secret === '', 500, 'Queue webhook secret is not configured.');

        abort_unless($this->valid(
            $request->getContent(),
            (string) $request->header('X-Queue-Signature'),
            $secret,
            (int) config('services.queue.signature_tolerance', 300),
        ), 401, 'Invalid signature.');

        return $next($request);
    }

    private function valid(string $body, string $header, string $secret, int $tolerance): bool
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[trim($key)] = trim($value);
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if (! ctype_digit($timestamp) || $signature === '' || abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "{$timestamp}.{$body}", $secret), $signature);
    }
}
