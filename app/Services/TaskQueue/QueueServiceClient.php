<?php

namespace App\Services\TaskQueue;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the BullMQ queue service API.
 */
class QueueServiceClient
{
    /**
     * @param  array{url: string, token: string|null, timeout: float, connect_timeout: float}  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * Send a task. Idempotent on `id`: re-sending an existing id returns the existing task.
     *
     * @param  array{id: string, queue: string, name: string, payload: array<string, mixed>, callback_url?: string|null, meta?: array<string, mixed>, options?: array<string, mixed>}  $task
     * @return array<string, mixed>
     */
    public function dispatch(array $task): array
    {
        $task['options'] = (object) ($task['options'] ?? []);
        $task['payload'] = (object) $task['payload'];

        return $this->send('dispatch', fn (PendingRequest $http) => $http->post('/tasks', array_filter(
            $task,
            fn ($value) => $value !== null,
        )))->json();
    }

    /**
     * Run a task and block until it finishes (`POST /tasks?wait=`). For interactive reads.
     * Throws QueueServiceException on transport errors, 503 (no workers) and 504 (timed out).
     *
     * @param  array{queue: string, name: string, payload: array<string, mixed>, options?: array<string, mixed>}  $task
     * @return array<string, mixed> The finished task: `state` is "completed" (with `result`) or "failed" (with `error`).
     */
    public function call(array $task, int $waitMs): array
    {
        $task['options'] = (object) ($task['options'] ?? []);
        $task['payload'] = (object) $task['payload'];

        // The HTTP timeout must outlast the server-side wait.
        $timeout = $waitMs / 1000 + $this->config['timeout'];

        $body = $this->send('call', fn (PendingRequest $http) => $http
            ->timeout($timeout)
            ->post('/tasks?wait='.$waitMs, $task))->json();

        return is_array($body) ? $body : [];
    }

    /**
     * Fetch a task's live state, or null if the queue service no longer has it.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $queue, string $id): ?array
    {
        $response = $this->send('find', fn (PendingRequest $http) => $http->get('/tasks/'.rawurlencode($queue).'/'.rawurlencode($id)), allow404: true);

        return $response->status() === 404 ? null : $response->json();
    }

    /**
     * Remove a task that is not yet running. Returns false if it was already gone.
     */
    public function cancel(string $queue, string $id): bool
    {
        $response = $this->send('cancel', fn (PendingRequest $http) => $http->delete('/tasks/'.rawurlencode($queue).'/'.rawurlencode($id)), allow404: true);

        return $response->status() !== 404;
    }

    /**
     * Probe the service. Never throws: connection problems are reported in the result.
     *
     * @return array{reachable: bool, latency_ms: int|null, queues: list<array{name: string, workers: int, waiting: int, active: int, delayed: int, failed: int}>, error: string|null}
     */
    public function health(): array
    {
        $started = hrtime(true);

        try {
            $response = Http::baseUrl(rtrim($this->config['url'], '/'))
                ->acceptJson()
                ->timeout(min($this->config['timeout'], 3))
                ->connectTimeout(min($this->config['connect_timeout'], 2))
                ->get('/health');
        } catch (ConnectionException $e) {
            return ['reachable' => false, 'latency_ms' => null, 'queues' => [], 'error' => $e->getMessage()];
        }

        $latency = (int) round((hrtime(true) - $started) / 1e6);
        $queues = $response->json('queues');

        return [
            'reachable' => $response->successful(),
            'latency_ms' => $latency,
            'queues' => is_array($queues) ? $this->parseQueues($queues) : [],
            'error' => $response->successful() ? null : "HTTP {$response->status()}: ".($response->json('error') ?? $response->body()),
        ];
    }

    /**
     * @param  array<mixed>  $queues
     * @return list<array{name: string, workers: int, waiting: int, active: int, delayed: int, failed: int}>
     */
    private function parseQueues(array $queues): array
    {
        $parsed = [];

        foreach ($queues as $queue) {
            if (! is_array($queue) || ! is_string($queue['name'] ?? null)) {
                continue;
            }

            $int = fn (string $key): int => is_int($queue[$key] ?? null) ? $queue[$key] : 0;

            $parsed[] = [
                'name' => $queue['name'],
                'workers' => $int('workers'),
                'waiting' => $int('waiting'),
                'active' => $int('active'),
                'delayed' => $int('delayed'),
                'failed' => $int('failed'),
            ];
        }

        return $parsed;
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(string $action, callable $request, bool $allow404 = false): Response
    {
        $http = Http::baseUrl(rtrim($this->config['url'], '/'))
            ->withToken((string) $this->config['token'])
            ->acceptJson()
            ->timeout($this->config['timeout'])
            ->connectTimeout($this->config['connect_timeout']);

        try {
            $response = $request($http);
        } catch (ConnectionException $e) {
            throw QueueServiceException::unreachable($action, $e);
        }

        if ($response->successful() || ($allow404 && $response->status() === 404)) {
            return $response;
        }

        throw QueueServiceException::requestFailed($action, $response->status(), $response->body());
    }
}
