<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::exec(
    '/var/www/html/crawler/.venv-docker/bin/python /var/www/html/crawler/fetch_mhi_job.py'
)->dailyAt('05:00');

Schedule::command('crawler:import-job')
    ->dailyAt('05:05');
