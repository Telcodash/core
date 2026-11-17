<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\QueueTask;
use App\Services\TaskQueue\TaskQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives signed lifecycle events from the queue service (see VerifyQueueSignature).
 */
class QueueWebhookController extends Controller
{
    public function __invoke(Request $request, TaskQueue $tasks): JsonResponse
    {
        /** @var array{event: 'active'|'progress'|'completed'|'failed', task_id: string, queue: string, attempts_made?: int, progress?: mixed, result?: mixed, error?: string|null} $data */
        $data = $request->validate([
            'event' => ['required', 'in:active,progress,completed,failed'],
            'task_id' => ['required', 'string'],
            'queue' => ['required', 'string'],
            'attempts_made' => ['sometimes', 'integer', 'min:0'],
            'progress' => ['sometimes', 'nullable'],
            'result' => ['sometimes', 'nullable'],
            'error' => ['sometimes', 'nullable', 'string'],
        ]);

        // A 404 tells the queue service to stop retrying this delivery.
        $task = QueueTask::query()
            ->whereKey($data['task_id'])
            ->where('queue', $data['queue'])
            ->firstOrFail();

        $applied = $tasks->record($task, $data['event'], $data);

        return response()->json(['applied' => $applied]);
    }
}
