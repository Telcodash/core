<?php

namespace Tests\Feature\TaskQueue;

use App\Enums\QueueTaskStatus;
use App\Events\QueueTaskCompleted;
use App\Events\QueueTaskFailed;
use App\Events\QueueTaskProgressed;
use App\Models\QueueTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class QueueWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'webhook-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.queue.webhook_secret' => self::SECRET]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function sendWebhook(array $body, ?string $signature = null, ?int $timestamp = null): TestResponse
    {
        $json = (string) json_encode($body);
        $timestamp ??= time();
        $signature ??= 't='.$timestamp.',v1='.hash_hmac('sha256', "{$timestamp}.{$json}", self::SECRET);

        return $this->call('POST', route('webhooks.queue'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_QUEUE_SIGNATURE' => $signature,
        ], content: $json);
    }

    private function queuedTask(): QueueTask
    {
        return QueueTask::create([
            'queue' => 'default',
            'name' => 'example.sleep',
            'payload' => [],
            'status' => QueueTaskStatus::Queued,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function event(QueueTask $task, string $event, array $extra = []): array
    {
        return $extra + ['event' => $event, 'task_id' => $task->id, 'queue' => $task->queue, 'name' => $task->name, 'attempts_made' => 1];
    }

    public function test_lifecycle_events_update_the_task(): void
    {
        Event::fake([QueueTaskProgressed::class, QueueTaskCompleted::class]);
        $task = $this->queuedTask();

        $this->sendWebhook($this->event($task, 'active'))->assertOk();
        $this->assertSame(QueueTaskStatus::Active, $task->fresh()?->status);
        $this->assertNotNull($task->fresh()?->started_at);

        $this->sendWebhook($this->event($task, 'progress', ['progress' => ['percent' => 50]]))->assertOk();
        $this->assertSame(['percent' => 50], $task->fresh()?->progress);
        Event::assertDispatched(QueueTaskProgressed::class);

        $this->sendWebhook($this->event($task, 'completed', ['result' => ['slept' => 10]]))
            ->assertOk()
            ->assertJson(['applied' => true]);

        $task->refresh();
        $this->assertSame(QueueTaskStatus::Completed, $task->status);
        $this->assertSame(['slept' => 10], $task->result);
        $this->assertNotNull($task->finished_at);
        Event::assertDispatched(QueueTaskCompleted::class, fn (QueueTaskCompleted $e) => $e->task->is($task));
    }

    public function test_failed_event_records_the_error(): void
    {
        Event::fake([QueueTaskFailed::class]);
        $task = $this->queuedTask();

        $this->sendWebhook($this->event($task, 'failed', ['error' => 'boom', 'attempts_made' => 3]))->assertOk();

        $task->refresh();
        $this->assertSame(QueueTaskStatus::Failed, $task->status);
        $this->assertSame('boom', $task->error);
        $this->assertSame(3, $task->attempts_made);
        Event::assertDispatched(QueueTaskFailed::class);
    }

    public function test_late_events_after_completion_are_ignored(): void
    {
        $task = $this->queuedTask();

        $this->sendWebhook($this->event($task, 'completed', ['result' => 1]))->assertOk();
        $this->sendWebhook($this->event($task, 'active'))->assertOk()->assertJson(['applied' => false]);

        $this->assertSame(QueueTaskStatus::Completed, $task->fresh()?->status);
    }

    public function test_invalid_or_stale_signatures_are_rejected(): void
    {
        $task = $this->queuedTask();
        $body = $this->event($task, 'completed');

        $this->sendWebhook($body, signature: 't=1,v1=deadbeef')->assertUnauthorized();
        $this->sendWebhook($body, signature: '')->assertUnauthorized();
        $this->sendWebhook($body, timestamp: time() - 3600)->assertUnauthorized();

        $this->assertSame(QueueTaskStatus::Queued, $task->fresh()?->status);
    }

    public function test_unknown_tasks_return_404_so_the_service_stops_retrying(): void
    {
        $this->sendWebhook([
            'event' => 'completed', 'task_id' => '0198f000-0000-7000-8000-000000000000', 'queue' => 'default',
        ])->assertNotFound();
    }

    public function test_webhook_does_not_require_csrf_or_session(): void
    {
        $task = $this->queuedTask();

        $this->sendWebhook($this->event($task, 'active'))
            ->assertOk()
            ->assertCookieMissing(config('session.cookie'));
    }
}
