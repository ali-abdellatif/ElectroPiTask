<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * List the authenticated user's projects, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $request->user()
            ->projects()
            ->withCount('tasks')
            ->latest()
            ->paginate($this->perPage($request));

        return ProjectResource::collection($projects);
    }

    /**
     * Create a project owned by the authenticated user.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return ProjectResource::make($project->loadCount('tasks'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return ProjectResource::make($project->loadCount('tasks'));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return ProjectResource::make($project->loadCount('tasks'));
    }

    public function destroy(Project $project): Response
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }

    /**
     * Clamp the client-supplied page size to a sane range, so a caller cannot
     * ask for every row at once. Anything non-numeric falls back to the default
     * rather than collapsing to a single row.
     */
    private function perPage(Request $request): int
    {
        $perPage = $request->query('per_page');

        if (! is_numeric($perPage)) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min((int) $perPage, self::MAX_PER_PAGE));
    }
}
