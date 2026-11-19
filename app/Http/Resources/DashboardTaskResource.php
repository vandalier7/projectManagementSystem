<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Task shape for the cross-project dashboard widgets. Each row can belong to a
 * different project, so it carries its own project summary and lead id.
 *
 * `previous_task` is always null here: the widgets only list open tasks, and
 * the modal fetches the revision chain on demand from /tasks/{task}/history.
 */
class DashboardTaskResource extends TaskResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'previous_task' => null,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'project_lead_id' => $this->project?->lead_id,
        ];
    }
}
