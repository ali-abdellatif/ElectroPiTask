<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Once a day rather than hourly: overdue is measured in whole days, so a task
// can only start being late at a day boundary.
Schedule::command('tasks:notify-overdue')->dailyAt('08:00');
