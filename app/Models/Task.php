<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Mirror the column defaults, so a freshly created model carries them
     * straight away instead of only after it is re-read from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => TaskPriority::Medium->value,
        'status' => TaskStatus::Todo->value,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'priority',
        'status',
        'due_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_date' => 'date',
        ];
    }

    /**
     * The project the task belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Narrow to a single status. A null filter is a no-op, so callers can apply
     * it unconditionally instead of branching.
     *
     * Columns are qualified because these scopes also run on the cross-project
     * listing, which joins through `projects` — and that table has a `status`
     * column of its own.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeWithStatus(Builder $query, ?TaskStatus $status): void
    {
        $query->when($status, fn (Builder $q) => $q->where($q->qualifyColumn('status'), $status));
    }

    /**
     * Narrow to a single priority. A null filter is a no-op.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeWithPriority(Builder $query, ?TaskPriority $priority): void
    {
        $query->when($priority, fn (Builder $q) => $q->where($q->qualifyColumn('priority'), $priority));
    }

    /**
     * Narrow to tasks whose title contains the given term. A blank term is a
     * no-op. Any wildcards the client sends simply widen the match rather than
     * being treated as literals.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when(filled($term), fn (Builder $q) => $q->where($q->qualifyColumn('title'), 'like', "%{$term}%"));
    }

    /**
     * Narrow to tasks that are past their due date and still open.
     *
     * This is the query-side twin of {@see self::isOverdue()}; the two
     * definitions must stay identical, or a task can be flagged overdue in a
     * listing while going uncounted on the dashboard.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('due_date'))
            ->where($query->qualifyColumn('status'), '!=', TaskStatus::Done)
            ->whereDate($query->qualifyColumn('due_date'), '<', today());
    }

    /**
     * Whether the task's deadline has passed while it is still open.
     *
     * Due dates are day-granular, so a task is overdue only once the day itself
     * has passed — something due today is not late yet. The dashboard's overdue
     * query must stay in step with this definition.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== TaskStatus::Done
            && $this->due_date->isBefore(today());
    }
}
