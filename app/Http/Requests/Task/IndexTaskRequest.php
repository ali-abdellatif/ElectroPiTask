<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
{
    /**
     * Ownership of the parent project is enforced by the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Filters are validated rather than silently ignored: `?status=blah` is a
     * client mistake, and answering it with an empty list would look like a
     * legitimate "no results" instead of a bad request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(TaskPriority::class)],
        ];
    }

    public function status(): ?TaskStatus
    {
        return $this->enum('status', TaskStatus::class);
    }

    public function priority(): ?TaskPriority
    {
        return $this->enum('priority', TaskPriority::class);
    }
}
