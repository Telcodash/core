<?php

namespace Tests\Feature\TaskQueue;

use App\Enums\QueueTaskStatus;
use App\Events\QueueTaskCompleted;
use App\Events\QueueTaskFailed;
use App\Models\QueueTask;
use App\Models\Team;
use App\Models\User;
use App\Services\TaskQueue\TaskQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaskQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.queue.url' => 'http://queue.test',
            'services.queue.token' => 'test-token',
            'services.queue.callback_url' => null,
        ]);
    }

    private function tasks(): TaskQueue
    {
        return $this->app->make(TaskQueue::class);
    }

    public function test_dispatch_records_the_task_and_sends_it_with_its_id(): void
    {
        Http::fake(['queue.test/tasks' => Http::response(['id' => 'x', 'state' => 'waiting'], 202)]);

        $user = User::factory()->create();
        $team = Team::firstOrFail();

        $task = $this->tasks()->dispatch('example.echo', ['a' => 1], subject: $team, user: $user, options: ['attempts' => 2]);

        $this->assertSame(QueueTaskStatus::Queued, $task->status);
        $this->assertNotNull($task->dispatched_at);
        $this->assertTrue($task->subject->is($team));
        $this->assertSame($user->id, $task->user_id);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://queue.test/tasks'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && json_decode($request->body(), true) === [
                'id' => $task->id,
                'queue' => 'default',
                'name' => 'example.echo',
                'payload' => ['a' => 1],
                'callback_url' => route('webhooks.queue'),
                'meta' => ['source' => 'core'],
                'options' => ['attempts' => 2],
            ]);
    }

    public function test_a_webhook_that_lands_before_dispatch_returns_is_not_overwritten(): void
    {
        Http::fake(function (Request $request) {
            // Simulates a worker finishing and its webhook arriving mid-request.
            $task = QueueTask::findOrFail($request['id']);
            $this->tasks()->record($task, 'completed', ['result' => ['fast' => true]]);

            return Http::response(['state' => 'waiting'], 202);
        });

        $task = $this->tasks()->dispatch('example.echo');

        $this->assertSame(QueueTaskStatus::Completed, $task->status);
        $this->assertSame(QueueTaskStatus::Completed, $task->fresh()?->status);
        $this->assertSame(['fast' => true], $task->result);
    }

    public function test_dispatch_inside_a_transaction_waits_for_commit(): void
    {
        Http::fake(['queue.test/*' => Http::response(['state' => 'waiting'], 202)]);

        DB::transaction(function () {
            $this->tasks()->dispatch('example.echo');
            Http::assertNothingSent();
        });

        Http::assertSentCount(1);
    }

    public function test_unreachable_service_marks_task_for_retry_and_sync_resends_it(): void
    {
        Http::fakeSequence('queue.test/*')
            ->pushStatus(503)
            ->push(['state' => 'waiting'], 202);

        $task = $this->tasks()->dispatch('example.echo');

        $this->assertSame(QueueTaskStatus::DispatchFailed, $task->status);

        $this->artisan('tasks:sync')->assertSuccessful();

        $this->assertSame(QueueTaskStatus::Queued, $task->fresh()?->status);
    }

    public function test_rejected_dispatch_fails_the_task_permanently(): void
    {
        Event::fake([QueueTaskFailed::class]);
        Http::fake(['queue.test/*' => Http::response(['error' => 'No processor'], 422)]);

        $task = $this->tasks()->dispatch('nope');

        $this->assertSame(QueueTaskStatus::Failed, $task->status);
        $this->assertStringContainsString('No processor', (string) $task->error);
        Event::assertDispatched(QueueTaskFailed::class);
    }

    public function test_sync_polls_stale_tasks_and_applies_the_remote_result(): void
    {
        Event::fake([QueueTaskCompleted::class]);
        Http::fake([
            'queue.test/tasks' => Http::response(['state' => 'waiting'], 202),
            'queue.test/tasks/default/*' => Http::response(['state' => 'completed', 'result' => ['ok' => true], 'attempts_made' => 1]),
        ]);

        $task = $this->tasks()->dispatch('example.echo');
        $this->travel(15)->minutes();

        $this->artisan('tasks:sync')->assertSuccessful();

        $task->refresh();
        $this->assertSame(QueueTaskStatus::Completed, $task->status);
        $this->assertSame(['ok' => true], $task->result);
        Event::assertDispatched(QueueTaskCompleted::class);
    }

    public function test_cancel_removes_a_queued_task(): void
    {
        Http::fake([
            'queue.test/tasks' => Http::response(['state' => 'waiting'], 202),
            'queue.test/tasks/default/*' => Http::response(null, 204),
        ]);

        $task = $this->tasks()->dispatch('example.echo');

        $this->assertTrue($this->tasks()->cancel($task));
        $this->assertSame(QueueTaskStatus::Cancelled, $task->fresh()?->status);
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE');
    }

    public function test_terminal_states_are_never_overwritten(): void
    {
        $task = QueueTask::create(['queue' => 'default', 'name' => 'x', 'payload' => [], 'status' => QueueTaskStatus::Completed]);

        $this->assertFalse($this->tasks()->record($task, 'active'));
        $this->assertFalse($this->tasks()->record($task, 'failed', ['error' => 'late']));
        $this->assertSame(QueueTaskStatus::Completed, $task->fresh()?->status);
    }
}
