<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leave:post')->hourly()->withoutOverlapping();

// Noon, not midnight: an office PC may be off at night.
Schedule::command('dtr:backup')->dailyAt('12:00')->withoutOverlapping();
