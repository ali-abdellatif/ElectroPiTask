<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the figures produced by DashboardService.
 *
 * The keys are listed explicitly rather than passed through, so the response
 * shape is fixed here and a change to the service cannot silently alter the
 * public contract.
 */
class DashboardResource extends JsonResource
{
    /**
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_projects' => $this->resource['total_projects'],
            'active_projects' => $this->resource['active_projects'],
            'total_tasks' => $this->resource['total_tasks'],
            'completed_tasks' => $this->resource['completed_tasks'],
            'pending_tasks' => $this->resource['pending_tasks'],
            'overdue_tasks' => $this->resource['overdue_tasks'],
        ];
    }
}
