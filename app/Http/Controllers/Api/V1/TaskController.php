<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesPageSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    use ResolvesPageSize;

    /**
     * List the tasks of a project the user owns, newest first.
     *
     * Filters compose: each scope is a no-op when its filter is absent, so any
     * combination of them narrows the same query.
     */
    public function index(IndexTaskRequest $request, Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $tasks = $project->tasks()
            ->withStatus($request->status())
            ->withPriority($request->priority())
            ->latest()
            ->paginate($this->perPage($request));

        return TaskResource::collection($tasks);
    }

    /**
     * Add a task to a project the user owns.
     */
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $task = $project->tasks()->create($request->validated());

        return TaskResource::make($task)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return TaskResource::make($task);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return TaskResource::make($task);
    }

    public function destroy(Task $task): Response
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
