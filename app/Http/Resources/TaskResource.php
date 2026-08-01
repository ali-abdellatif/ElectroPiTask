<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            // Only present when the controller eager loaded the relation.
            'project' => ProjectResource::make($this->whenLoaded('project')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
