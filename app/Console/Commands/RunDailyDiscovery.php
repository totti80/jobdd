<?php

namespace App\Console\Commands;

use App\Services\DailyDiscoveryService;
use Illuminate\Console\Command;
use Throwable;

class RunDailyDiscovery extends Command
{
    protected $signature = 'jobdd:discover-daily {--dry-run : Preview without database writes}
        {--provider= : careerjet, recruit_agent or meitec_next}
        {--occupation= : Canonical occupation} {--region= : Canonical prefecture}';

    protected $description = 'Run daily 12-cell Discovery and write an internal quality report';

    public function handle(DailyDiscoveryService $service): int
    {
        try {
            $result = $service->run((bool) $this->option('dry-run'), $this->option('provider'), $this->option('occupation'), $this->option('region'));
            $this->line('Status: '.$result['status']);
            $this->line('Report: '.$result['report_path']);

            return $result['status'] === 'success' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error(in_array($e->getMessage(), ['InvalidCell', 'InvalidProvider', 'AlreadyRunning'], true) ? $e->getMessage() : class_basename($e));

            return self::FAILURE;
        }
    }
}
