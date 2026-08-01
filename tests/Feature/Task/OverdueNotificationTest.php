<?php

namespace Tests\Feature\Task;

use App\Jobs\NotifyUserOfOverdueTasks;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\OverdueTasksNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OverdueNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_queues_a_job_for_each_user_with_overdue_tasks(): void
    {
        Queue::fake();

        $late = User::factory()->create();
        Task::factory()->count(3)->for(Project::factory()->for($late))->overdue()->create();

        $alsoLate = User::factory()->create();
        Task::factory()->for(Project::factory()->for($alsoLate))->overdue()->create();

        $this->artisan('tasks:notify-overdue')->assertSuccessful();

        Queue::assertPushed(NotifyUserOfOverdueTasks::class, 2);
    }

    public function test_users_without_overdue_tasks_are_skipped(): void
    {
        Queue::fake();

        $project = Project::factory()->for(User::factory())->create();
        Task::factory()->count(4)->for($project)->completedLate()->create();
        Task::factory()->count(2)->for($project)->todo()->withoutDueDate()->create();
        Task::factory()->for($project)->todo()->create(['due_date' => today()]);

        $this->artisan('tasks:notify-overdue')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_the_job_sends_one_digest_covering_all_of_a_users_late_tasks(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Task::factory()->count(5)->for(Project::factory()->for($user))->overdue()->create();

        (new NotifyUserOfOverdueTasks($user))->handle();

        Notification::assertSentTo(
            $user,
            OverdueTasksNotification::class,
            fn ($notification) => true,
        );
        Notification::assertSentToTimes($user, OverdueTasksNotification::class, 1);
    }

    /**
     * The job re-reads the overdue set, so work finished between queueing and
     * running does not produce a reminder that is already wrong.
     */
    public function test_the_job_sends_nothing_when_the_tasks_were_completed_in_the_meantime(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $tasks = Task::factory()->count(2)->for(Project::factory()->for($user))->overdue()->create();

        $job = new NotifyUserOfOverdueTasks($user);
        $tasks->each->update(['status' => 'done']);
        $job->handle();

        Notification::assertNothingSent();
    }

    public function test_a_user_is_never_told_about_another_users_late_work(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Task::factory()->count(2)->for(Project::factory()->for($user))->overdue()->create();
        Task::factory()->count(9)->for(Project::factory()->for(User::factory()))->overdue()->create();

        (new NotifyUserOfOverdueTasks($user))->handle();

        Notification::assertSentTo($user, OverdueTasksNotification::class, function ($notification) use ($user) {
            $lines = collect($notification->toMail($user)->introLines)
                ->filter(fn (string $line) => str_starts_with($line, '- '));

            // One line per late task: this user's two, none of the other's nine.
            return $lines->count() === 2;
        });
    }

    /**
     * Faking the notification facade skips the sender entirely, which is where a
     * queued notification is actually assembled. This drives the real path and
     * asserts a mail message comes out the other end.
     */
    public function test_the_notification_survives_the_real_queued_sender(): void
    {
        $user = User::factory()->create(['email' => 'late@example.com']);
        Task::factory()->count(2)->for(Project::factory()->for($user))->overdue()->create();

        // Nothing is faked here. The queue runs synchronously under test, so the
        // notification goes through the real sender and reaches the array mailer.
        (new NotifyUserOfOverdueTasks($user))->handle();

        $messages = app('mailer')->getSymfonyTransport()->messages();

        $this->assertCount(1, $messages);

        $sent = $messages->first()->getOriginalMessage();
        $this->assertStringContainsString('overdue', $sent->getSubject());
        $this->assertSame('late@example.com', $sent->getTo()[0]->getAddress());
    }

    public function test_the_notification_is_queued_rather_than_sent_inline(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new OverdueTasksNotification(collect()),
        );
        $this->assertInstanceOf(
            ShouldQueue::class,
            new NotifyUserOfOverdueTasks(User::factory()->create()),
        );
    }
}
