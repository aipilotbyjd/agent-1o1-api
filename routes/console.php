<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('triggers:fire-due-schedule')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('triggers:queue-due-polling')->everyMinute()->withoutOverlapping()->onOneServer();

// The backstop for events whose job never completed. Runs often enough that a
// lost event is recovered in minutes, and is a no-op when nothing is stranded.
Schedule::command('triggers:reconcile')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

Schedule::command('workflows:expire-waiting-callbacks')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('billing:rollover-credits')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('billing:notify-trial-ending')->daily()->withoutOverlapping()->onOneServer();
