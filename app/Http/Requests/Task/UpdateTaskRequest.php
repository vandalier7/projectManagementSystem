<?php

namespace App\Http\Requests\Task;

use App\Services\TaskLifecycle\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Who may perform the update is decided by TaskLifecycleEngine, using the
 * authenticated user — never a user id sent in the request body.
 */
class UpdateTaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:255',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
            'priority' => 'sometimes|string|max:255',
            'status' => ['sometimes', Rule::in(TaskStatus::ALL)],
            'submission_link' => 'sometimes|string|max:255',
            'submission_notes' => 'sometimes|nullable|string',
            'review_comment' => 'sometimes|nullable|string',
            'due_date' => 'nullable|date',

            // Overrides for the revision task created when a submission is rejected.
            'new_title' => 'sometimes|nullable|string|max:255',
            'new_description' => 'sometimes|nullable|string|max:255',
            'new_assigned_to' => 'sometimes|nullable|exists:users,id',
            'new_priority' => 'sometimes|nullable|string|max:255',
            'new_due_date' => 'sometimes|nullable|date',
        ];
    }

    /** @return array{new_title?: string, new_description?: string, new_assigned_to?: int, new_priority?: string, new_due_date?: string} */
    public function revisionOverrides(): array
    {
        return array_filter(
            $this->safe()->only(['new_title', 'new_description', 'new_assigned_to', 'new_priority', 'new_due_date']),
            fn ($value) => $value !== null,
        );
    }

    public function changes(): array
    {
        return $this->safe()->except(['new_title', 'new_description', 'new_assigned_to', 'new_priority', 'new_due_date']);
    }
}
