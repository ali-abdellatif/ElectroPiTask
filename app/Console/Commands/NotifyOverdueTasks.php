<?php

namespace App\Console\Commands;

use App\Jobs\NotifyUserOfOverdueTasks;
use App\Models\User;
use Illuminate\Console\Command;

class NotifyOverdueTasks extends Command
{
    protected $signature = 'tasks:notify-overdue';

    protected $description = 'Queue a digest of overdue tasks for every user who has any';

    public function handle(): int
    {
        $queued = 0;

        // Chunked so the command holds a bounded amount of memory no matter how
        // many users the system grows to.
        User::whereHas('tasks', fn ($query) => $query->overdue())
            ->chunkById(100, function ($users) use (&$queued) {
                foreach ($users as $user) {
                    NotifyUserOfOverdueTasks::dispatch($user);
                    $queued++;
                }
            });

        $this->info("Queued overdue reminders for {$queued} ".str('user')->plural($queued).'.');

        return self::SUCCESS;
    }
}
