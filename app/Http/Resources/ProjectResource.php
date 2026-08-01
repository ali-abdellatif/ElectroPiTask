<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
            // Only present when the controller asked for it with withCount(),
            // so the resource never triggers a query of its own.
            'tasks_count' => $this->whenCounted('tasks'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
