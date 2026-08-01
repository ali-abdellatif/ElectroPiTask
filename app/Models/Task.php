<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
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
