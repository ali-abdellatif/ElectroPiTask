<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.7)->paragraph(),
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'status' => fake()->randomElement(TaskStatus::cases()),
            'due_date' => fake()->optional(0.8)->dateTimeBetween('-2 weeks', '+2 months'),
        ];
    }

    public function todo(): static
    {
        return $this->state(['status' => TaskStatus::Todo]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TaskStatus::InProgress]);
    }

    public function done(): static
    {
        return $this->state(['status' => TaskStatus::Done]);
    }

    public function priority(TaskPriority $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    /**
     * A task that is past its due date and not yet done.
     */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'status' => fake()->randomElement([TaskStatus::Todo, TaskStatus::InProgress]),
        ]);
    }

    /**
     * A task with a past due date that is already done, so it is not overdue.
     */
    public function completedLate(): static
    {
        return $this->state(fn () => [
            'due_date' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'status' => TaskStatus::Done,
        ]);
    }

    public function withoutDueDate(): static
    {
        return $this->state(['due_date' => null]);
    }
}
