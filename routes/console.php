<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('crawler:run-mhi')
    ->dailyAt('05:00')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping();

Schedule::command('jobdd:discover-daily')
    ->dailyAt(config('discovery.time'))
    ->timezone(config('discovery.timezone'))
    ->withoutOverlapping(1440)
    ->when(fn () => (bool) config('discovery.enabled'));
