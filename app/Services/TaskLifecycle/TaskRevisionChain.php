<?php

namespace App\Services\TaskLifecycle;

use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Loads revision chains (task → previous_task → previous_task …) to any depth.
 *
 * This replaces a hardcoded 10-level `previousTask.previousTask…` eager load,
 * which silently cut off history after the 10th rejection. Here each level is
 * one query for the whole batch, so the cost is (chain depth) queries, not
 * (chain depth × tasks).
 */
class TaskRevisionChain
{
    /**
     * Set the `previousTask` relation (with `assignee`) on every task in the
     * collection, recursively, until every chain ends.
     *
     * @param  iterable<Task>  $tasks
     */
    public function attach(iterable $tasks): void
    {
        $level = collect($tasks);

        // Every ancestor fetched so far, by id. Ancestors shared between
        // chains are loaded once, and only newly fetched ones are walked
        // further — which also stops a corrupt cyclic chain from looping.
        $fetched = collect();

        while ($level->isNotEmpty()) {
            $missing = $level->pluck('previous_task_id')
                ->filter()
                ->reject(fn ($id) => $fetched->has($id))
                ->unique()
                ->values();

            $new = $missing->isEmpty()
                ? collect()
                : Task::with('assignee')->whereIn('id', $missing)->get()->keyBy('id');

            $fetched = $fetched->union($new);

            foreach ($level as $task) {
                $task->setRelation('previousTask', $fetched->get($task->previous_task_id));
            }

            $level = $new->values();
        }
    }

    /**
     * The chain for a single task, oldest-last, not including the task itself.
     *
     * @return Collection<int, Task>
     */
    public function ancestorsOf(Task $task): Collection
    {
        $this->attach([$task]);

        $chain = collect();
        $cursor = $task->previousTask;

        while ($cursor) {
            $chain->push($cursor);
            $cursor = $cursor->previousTask;
        }

        return $chain;
    }
}
