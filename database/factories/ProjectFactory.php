<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->catchPhrase(),
            'description' => fake()->optional(0.8)->paragraph(),
            'status' => fake()->randomElement(ProjectStatus::cases()),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => ProjectStatus::Active]);
    }

    public function completed(): static
    {
        return $this->state(['status' => ProjectStatus::Completed]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ProjectStatus::Archived]);
    }
}
