<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('books:send-stalled-reminders')->weeklyOn(1, '09:00');
Schedule::command('books:send-monthly-summaries')->monthlyOn(1, '08:00');
