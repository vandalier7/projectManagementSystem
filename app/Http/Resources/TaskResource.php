<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The flat task shape used by the history endpoint and the dashboard widgets.
 *
 * The assignee's name is masked as "Deleted User" when the assignee account
 * was deleted, unless the viewer is an admin or the project opted in to
 * showing deleted users' names.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'requires_submission' => $this->requires_submission,
            'sort_order' => $this->sort_order,
            'due_date' => $this->due_date,
            'assignee' => $this->assigneeFor($request),
            'submission_link' => $this->submission_link,
            'submission_notes' => $this->submission_notes,
            'submitted_at' => $this->submitted_at,
            'review_comment' => $this->review_comment,
            'reviewed_at' => $this->reviewed_at,
            'completed_date' => $this->completed_date,
        ];
    }

    private function assigneeFor(Request $request): ?array
    {
        $assignee = $this->assignee;

        if (!$assignee) {
            return null;
        }

        $viewer = $request->user();
        $canSeeRealName = !$assignee->trashed()
            || $viewer?->isAdmin()
            || ($this->project?->display_deleted_user_names ?? false);

        return [
            'id' => $assignee->id,
            'full_name' => $canSeeRealName ? $assignee->full_name : 'Deleted User',
        ];
    }
}
