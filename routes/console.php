<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('triggers:fire-due-schedule')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('triggers:queue-due-polling')->everyMinute()->withoutOverlapping()->onOneServer();
