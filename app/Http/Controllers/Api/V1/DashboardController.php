<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Summary figures for the authenticated user's own workload.
     */
    public function __invoke(Request $request): DashboardResource
    {
        return DashboardResource::make($this->dashboard->statsFor($request->user()));
    }
}
