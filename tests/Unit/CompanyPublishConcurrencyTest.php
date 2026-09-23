<?php

use App\Models\JobPosting;
use App\Services\CompanyJobAuthoringData;
use App\Services\CompanyJobPublishService;
use App\Services\CompanyJobReviewService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);
require_once __DIR__.'/../Support/PublishFixture.php';

test('two connections serialize approval and only one publication commits', function () {
    expect(DB::connection()->getDatabaseName())->toBe('testing');
    Mail::fake();
    [$owner,$job,$admin] = publishFixture();
    app(CompanyJobReviewService::class)->request($job, $owner);
    $token = app(CompanyJobAuthoringData::class)->token($job->fresh());
    $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'testing') exit(90);
$job = App\Models\JobPosting::findOrFail((int)$argv[1]);
$admin = App\Models\User::findOrFail((int)$argv[2]);
Illuminate\Support\Facades\DB::connection()->beforeExecuting(function ($sql) { if (str_contains($sql, 'for update')) { echo "locking\n"; flush(); } });
try { app(App\Services\CompanyJobPublishService::class)->approve($job, $admin, $argv[3]); echo "published\n"; }
catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { echo 'status:'.$e->getStatusCode()."\n"; }
PHP;
    $process = new Process([PHP_BINARY, '-r', $script, (string) $job->id, (string) $admin->id, $token], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'testing', 'DB_URL' => '', 'MAIL_MAILER' => 'array']);
    $process->setTimeout(20);
    DB::beginTransaction();
    try {
        JobPosting::query()->lockForUpdate()->findOrFail($job->id);
        $process->start();
        expect($process->waitUntil(fn ($type, $output) => str_contains($output, 'locking')))->toBeTrue();
        usleep(200000);
        expect($process->isRunning())->toBeTrue();
        app(CompanyJobPublishService::class)->approve($job, $admin, $token);
        DB::commit();
        $process->wait();
        expect($process->getExitCode())->toBe(0)->and($process->getOutput())->toContain('status:409')->not->toContain("published\n");
        expect($job->fresh()->review_status)->toBe('approved')->and($job->publishedProfile()->count())->toBe(1)->and($job->applicationRoutes()->count())->toBe(1);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if ($process->isRunning()) {
            $process->stop();
        }
    }
});
