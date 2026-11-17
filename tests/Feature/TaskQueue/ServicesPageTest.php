<?php

namespace Tests\Feature\TaskQueue;

use App\Enums\QueueTaskStatus;
use App\Models\QueueTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServicesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.queue.url' => 'http://queue.test',
            'services.queue.token' => 'test-token',
            'services.queue.webhook_secret' => null,
        ]);
    }

    public function test_only_administrators_can_access_services(): void
    {
        $manager = User::factory()->create();
        $task = QueueTask::create(['queue' => 'default', 'name' => 'x', 'payload' => []]);

        $this->get(route('services.index'))->assertRedirect(route('login'));
        $this->actingAs($manager)->get(route('services.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('services.queue.test'))->assertForbidden();
        $this->actingAs($manager)->get(route('services.queue.task', $task))->assertForbidden();
    }

    public function test_page_shows_queue_status_and_configuration(): void
    {
        Http::fake(['queue.test/health' => Http::response(['status' => 'ok', 'queues' => ['default', 'reports']])]);
        QueueTask::create(['queue' => 'default', 'name' => 'x', 'payload' => [], 'status' => QueueTaskStatus::Completed]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('services.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/services/index')
                ->where('queue.reachable', true)
                ->where('queue.queues', ['default', 'reports'])
                ->where('queue.error', null)
                ->where('queue.token_configured', true)
                ->where('queue.webhook_secret_configured', false)
                ->where('queue.callback_url', route('webhooks.queue'))
                ->where('queue.tasks_24h.completed', 1)
                ->where('queue.tasks_24h.failed', 0));
    }

    public function test_page_reports_an_unreachable_service(): void
    {
        Http::fake(['queue.test/health' => Http::failedConnection()]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('queue.reachable', false)
                ->where('queue.latency_ms', null)
                ->whereType('queue.error', 'string'));
    }

    public function test_test_dispatches_a_task_when_the_service_is_healthy(): void
    {
        Http::fake([
            'queue.test/health' => Http::response(['status' => 'ok', 'queues' => ['default']]),
            'queue.test/tasks' => Http::response(['state' => 'waiting'], 202),
        ]);

        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->postJson(route('services.queue.test'))
            ->assertOk()
            ->assertJsonPath('health.reachable', true)
            ->assertJsonPath('task.name', 'example.sleep')
            ->assertJsonPath('task.status', 'queued');

        $task = QueueTask::findOrFail($response->json('task.id'));
        $this->assertSame($admin->id, $task->user_id);
    }

    public function test_test_does_not_dispatch_when_the_service_is_down(): void
    {
        Http::fake(['queue.test/*' => Http::failedConnection()]);

        $this->actingAs(User::factory()->administrator()->create())
            ->postJson(route('services.queue.test'))
            ->assertOk()
            ->assertJsonPath('health.reachable', false)
            ->assertJsonPath('task', null);

        $this->assertDatabaseCount('queue_tasks', 0);
    }

    public function test_task_endpoint_returns_local_and_optionally_remote_state(): void
    {
        Http::fake(['queue.test/tasks/default/*' => Http::response(['state' => 'completed'])]);
        $task = QueueTask::create(['queue' => 'default', 'name' => 'example.sleep', 'payload' => [], 'status' => QueueTaskStatus::Active]);
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->getJson(route('services.queue.task', $task))
            ->assertOk()
            ->assertJsonPath('task.status', 'active')
            ->assertJsonPath('remote', null);

        Http::assertNothingSent();

        $this->actingAs($admin)->getJson(route('services.queue.task', ['task' => $task, 'remote' => 1]))
            ->assertJsonPath('remote.state', 'completed');
    }
}
