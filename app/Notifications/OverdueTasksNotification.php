<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class OverdueTasksNotification extends Notification implements ShouldQueue
{
    // Required alongside ShouldQueue: the notification sender reads the
    // connection, queue and delay properties this trait provides.
    use Queueable;

    /**
     * @param  Collection<int, Task>  $tasks
     */
    public function __construct(private readonly Collection $tasks) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->tasks->count();
        $noun = str('task')->plural($count);

        $message = (new MailMessage)
            ->subject("You have {$count} overdue {$noun}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$count} of your {$noun} passed the due date and are still open.");

        // A digest rather than one email per task: a user with twenty late
        // tasks should get one message, not twenty.
        foreach ($this->tasks as $task) {
            $message->line(sprintf(
                '- %s (due %s, in %s)',
                $task->title,
                $task->due_date->toFormattedDateString(),
                $task->project->name,
            ));
        }

        return $message->line('Open the dashboard to catch up.');
    }
}
