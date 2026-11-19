<?php

namespace App\Services\TaskLifecycle;

/**
 * The five states a task can be in. Lifecycle:
 *
 *   todo → in_progress → submitted → done
 *                ↑            │
 *                └─ takeback ─┤
 *                             └→ closed (rejected) → new revision task in todo
 *
 * Tasks that don't require submission (or whose assignee is the lead) can go
 * straight from in_progress to done.
 */
final class TaskStatus
{
    public const TODO = 'todo';
    public const IN_PROGRESS = 'in_progress';
    public const SUBMITTED = 'submitted';
    public const DONE = 'done';
    public const CLOSED = 'closed';

    public const ALL = [self::TODO, self::IN_PROGRESS, self::SUBMITTED, self::DONE, self::CLOSED];

    /** Statuses that end a task's life — it can no longer be edited by non-admins. */
    public const FINISHED = [self::DONE, self::CLOSED];

    /** Statuses in which task details are locked for leads (admins can still edit). */
    public const LOCKED = [self::SUBMITTED, self::DONE, self::CLOSED];
}
