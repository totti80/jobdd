<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class RunMhiCrawler extends Command
{
    protected $signature = 'crawler:run-mhi';

    protected $description = 'Run MHI crawler and import the fetched job data';

    public function handle(): int
    {
        $python = config('crawler.python');

        $script = base_path('crawler/fetch_mhi_job.py');

        try {
            /*
             * 古いJSONを先に消す。
             * Python取得失敗時に前回データを誤importしないため。
             */
            Storage::disk('local')->delete('crawler/mhi_job.json');

            if (! file_exists($script)) {
                throw new RuntimeException(
                    "Crawler script not found: {$script}"
                );
            }

            $this->info('Running MHI Python crawler...');

            $process = new Process([
                $python,
                $script,
            ]);

            $process->setWorkingDirectory(base_path());
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    trim($process->getErrorOutput())
                        ?: trim($process->getOutput())
                        ?: 'Python crawler failed.'
                );
            }

            /*
             * Pythonが正常終了してもJSONが無ければ失敗扱い。
             */
            if (! Storage::disk('local')->exists('crawler/mhi_job.json')) {
                throw new RuntimeException(
                    'Crawler finished but mhi_job.json was not created.'
                );
            }

            $this->info('Python crawler completed.');

            /*
             * 既存のImportCrawlerJobを呼び出す。
             * DB更新とcrawl_runsのsuccess/failed記録は
             * crawler:import-job側に任せる。
             */
            $exitCode = Artisan::call('crawler:import-job');

            $this->output->write(Artisan::output());

            if ($exitCode !== Command::SUCCESS) {
                return Command::FAILURE;
            }

            $this->info('MHI crawler run completed successfully.');

            return Command::SUCCESS;
        } catch (Throwable $e) {
            /*
             * Python段階で失敗した場合は、
             * importerまで到達しないのでここで履歴を残す。
             */
            DB::table('crawl_runs')->insert([
                'crawler_name' => 'mhi_job_crawler',
                'status' => 'failed',
                'started_at' => now(),
                'finished_at' => now(),
                'fetched_count' => 0,
                'created_count' => 0,
                'updated_count' => 0,
                'failed_count' => 1,
                'error_message' => $e->getMessage(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->error('MHI crawler failed.');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
