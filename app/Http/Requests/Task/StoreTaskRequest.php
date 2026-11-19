<?php

namespace App\Http\Requests\Task;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::find($this->input('project_id'));

        // A missing project falls through to validation (422), not a 403.
        return !$project || $this->user()->can('manageTasks', $project);
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'Only the project lead or an admin can create tasks.');
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'project_id' => 'required|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => 'nullable|string|max:255',
            'requires_submission' => 'nullable|boolean',
            'due_date' => 'nullable|date',
            'notify_all' => 'nullable|boolean',
        ];
    }
}
