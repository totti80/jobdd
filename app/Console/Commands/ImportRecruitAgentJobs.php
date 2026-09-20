<?php

namespace App\Console\Commands;

use App\Models\ApplicationRoute;
use App\Models\Company;
use App\Models\JobPosting;
use App\Services\DailyDiscoveryImporter;
use App\Services\DirectLookup\CompanyUrlEvidence;
use App\Services\OccupationNormalizer;
use App\Support\ProviderCapabilities;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportRecruitAgentJobs extends Command
{
    /**
     * リクルートエージェントのagency_id
     */
    private const AGENCY_ID = 8;

    /**
     * JSONファイル
     */
    private const JSON_PATH = 'crawler/recruit_agent_jobs.json';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawler:import-recruit-agent-jobs {--path= : JSON filename under storage/app/private/crawler} {--daily-input= : Internal daily payload filename} {--dry-run : Daily import preview only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description =
        'Import Recruit Agent crawled jobs into companies, job_postings and application_routes';

    /**
     * Execute the console command.
     */
    public function handle(
        OccupationNormalizer $occupationNormalizer
    ): int {
        if ($this->option('daily-input') || $this->option('dry-run')) {
            return app(DailyDiscoveryImporter::class)->command($this, 'recruit_agent');
        }

        if (! ProviderCapabilities::persistent('recruit_agent')) {
            $this->error('ProviderReadOnly: use jobdd:discover-daily for observations.');

            return self::FAILURE;
        }

        $this->info('=== Recruit Agent importer start ===');
        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | JSONファイル確認
        |--------------------------------------------------------------------------
        */

        $customPath = trim((string) $this->option('path'));

        $isPartialImport = $customPath !== '';

        if ($isPartialImport) {
            // ファイル名のみ許可。 ../ などのパストラバーサルは禁止。
            if (basename($customPath) !== $customPath) {
                $this->error('Invalid --path. Specify filename only.');

                return self::FAILURE;
            }

            $path = storage_path(
                'app/private/crawler/'.$customPath
            );
        } else {
            $path = storage_path(
                'app/private/'.self::JSON_PATH
            );
        }

        if (! file_exists($path)) {
            $this->error(
                'JSON file not found: '.$path
            );

            return self::FAILURE;
        }

        $this->line(
            'import file: '.basename($path)
        );

        $this->line(
            'import mode: '.($isPartialImport ? 'partial' : 'full')
        );

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | JSON読込
        |--------------------------------------------------------------------------
        */

        $raw = file_get_contents($path);

        if ($raw === false) {
            $this->error(
                'Failed to read JSON file.'
            );

            return self::FAILURE;
        }

        $data = json_decode(
            $raw,
            true
        );

        if (! is_array($data)) {
            $this->error(
                'Invalid JSON.'
            );

            return self::FAILURE;
        }

        $jobs = $data['jobs'] ?? null;

        if (! is_array($jobs)) {
            $this->error(
                'jobs array not found.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | クロール結果表示
        |--------------------------------------------------------------------------
        */

        $this->line(
            'source: '
                .($data['source_provider'] ?? 'unknown')
        );

        $this->line(
            'discovered: '
                .($data['discovered_job_count'] ?? count($jobs))
        );

        $this->line(
            'jobs in JSON: '
                .count($jobs)
        );

        $this->line(
            'completed: '
                .(($data['completed'] ?? false) ? 'true' : 'false')
        );

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | 集計
        |--------------------------------------------------------------------------
        */

        $companiesCreated = 0;

        $jobsCreated = 0;
        $jobsUpdated = 0;

        $routesCreated = 0;
        $routesUpdated = 0;

        $skipped = 0;
        $errors = 0;

        /*
        |--------------------------------------------------------------------------
        | Import
        |--------------------------------------------------------------------------
        */

        foreach ($jobs as $index => $row) {

            $number = $index + 1;

            try {

                /*
                |--------------------------------------------------------------------------
                | 必須項目
                |--------------------------------------------------------------------------
                */

                $companyName = $this->cleanString(
                    $row['company_name'] ?? null
                );

                $title = $this->cleanString(
                    $row['title'] ?? null
                );

                $region = $this->cleanString(
                    $row['region'] ?? null
                );

                $sourceUrl = $this->cleanString(
                    $row['source_url'] ?? null
                );

                $externalId = $this->cleanString(
                    $row['external_id'] ?? $row['id'] ?? $sourceUrl
                );

                if (
                    ! $companyName
                    || ! $title
                    || ! $region
                    || ! $sourceUrl
                ) {
                    $skipped++;

                    $this->warn(
                        sprintf(
                            '[%d/%d] skipped: missing required field',
                            $number,
                            count($jobs)
                        )
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | 各種正規化
                |--------------------------------------------------------------------------
                */

                $description = $this->cleanString(
                    $row['description'] ?? null
                );

                /*
|--------------------------------------------------------------------------
| JobDD共通職種正規化
|--------------------------------------------------------------------------
|
| crawlerの検索キーワード由来 occupation は使用しない。
| 求人タイトル + description から実際の職種を判定する。
|
*/

                $occupation = $occupationNormalizer->normalize(
                    title: $title,
                    description: $description
                );

                $employmentType = $this->cleanString(
                    $row['employment_type'] ?? null
                );

                $salaryMin = $this->normalizeSalary(
                    $row['salary_min'] ?? null
                );

                $salaryMax = $this->normalizeSalary(
                    $row['salary_max'] ?? null
                );

                /*
                |--------------------------------------------------------------------------
                | DB
                |--------------------------------------------------------------------------
                */

                DB::transaction(function () use (
                    $row,
                    $companyName,
                    $title,
                    $region,
                    $sourceUrl,
                    $externalId,
                    $occupation,
                    $description,
                    $employmentType,
                    $salaryMin,
                    $salaryMax,
                    &$companiesCreated,
                    &$jobsCreated,
                    &$jobsUpdated,
                    &$routesCreated,
                    &$routesUpdated
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | companies
                    |--------------------------------------------------------------------------
                    |
                    | 求人企業。
                    | リクルートエージェント自体ではない。
                    |
                    */

                    $company = Company::query()
                        ->where(
                            'name',
                            $companyName
                        )
                        ->first();

                    if (! $company) {

                        $company = Company::create([
                            'name' => $companyName,

                            'website_url' => null,

                            /*
                             * Recruit JSON-LDのindustryは
                             * 「職業紹介」になっているケースがあり、
                             * 求人企業の業種FACTではないため保存しない。
                             */
                            'industry' => null,

                            'region' => $region,
                        ]);

                        $companiesCreated++;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | job_postings
                    |--------------------------------------------------------------------------
                    |
                    | 同一企業・同一求人タイトル・同一地域なら
                    | 共通求人として扱う。
                    |
                    | これにより、
                    |
                    |   JobPosting
                    |      ├ Meitec
                    |      ├ Recruit
                    |      └ Ties
                    |
                    | の形にできる。
                    |
                    */

                    $jobPosting = JobPosting::query()
                        ->where('provider_key', 'recruit_agent')
                        ->where('external_id', $externalId)
                        ->first()
                        ?? JobPosting::query()
                            ->where(
                                'company_id',
                                $company->id
                            )
                            ->where(
                                'title',
                                $title
                            )
                            ->where(
                                'region',
                                $region
                            )
                            ->first();

                    if (! $jobPosting) {

                        $jobPosting = JobPosting::create([
                            'company_id' => $company->id,

                            'title' => $title,

                            'occupation' => $occupation,

                            /*
                             * Recruit側のindustryは
                             * 求人企業業種として信用できないのでnull。
                             */
                            'industry' => null,

                            'region' => $region,

                            'salary_min' => $salaryMin,

                            'salary_max' => $salaryMax,

                            'description' => $description,

                            'employment_type' => $employmentType,

                            'source_url' => $sourceUrl,
                            'provider_key' => 'recruit_agent',
                            'external_id' => $externalId,
                            'first_seen_at' => now(),
                            'last_seen_at' => now(),
                            'unavailable_at' => null,
                        ]);

                        $jobsCreated++;
                    } else {

                        /*
                         * 共通求人が既にある場合は、
                         * 既存FACTをむやみに消さない。
                         *
                         * Recruit側で確認できた値だけ補完する。
                         */

                        $updateData = [];

                        /*
 * 旧Crawlerでは検索キーワードをoccupationとして
 * 保存していたため、正規化結果が得られた場合は
 * 既存値も更新する。
 */
                        if (
                            $occupation !== null
                            && $jobPosting->occupation !== $occupation
                        ) {
                            $updateData['occupation'] =
                                $occupation;
                        }

                        if (
                            $jobPosting->salary_min === null
                            && $salaryMin !== null
                        ) {
                            $updateData['salary_min'] =
                                $salaryMin;
                        }

                        if (
                            $jobPosting->salary_max === null
                            && $salaryMax !== null
                        ) {
                            $updateData['salary_max'] =
                                $salaryMax;
                        }

                        if (
                            empty($jobPosting->description)
                            && $description
                        ) {
                            $updateData['description'] =
                                $description;
                        }

                        if (
                            empty($jobPosting->employment_type)
                            && $employmentType
                        ) {
                            $updateData['employment_type'] =
                                $employmentType;
                        }

                        if (
                            empty($jobPosting->source_url)
                            && $sourceUrl
                        ) {
                            $updateData['source_url'] =
                                $sourceUrl;
                        }

                        if (! empty($updateData)) {
                            $jobPosting->update(
                                $updateData
                            );
                        }

                        $updateData['provider_key'] = 'recruit_agent';
                        $updateData['external_id'] = $externalId;
                        $updateData['last_seen_at'] = now();
                        $updateData['unavailable_at'] = null;
                        $jobPosting->update($updateData);

                        $jobsUpdated++;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | application_routes
                    |--------------------------------------------------------------------------
                    |
                    | 求人そのものではなく
                    | 「リクルートエージェント経由で応募できる」
                    | という応募経路を保存する。
                    |
                    */

                    app(CompanyUrlEvidence::class)->save($jobPosting->id, $company->id, $row, 'recruit_agent');

                    $route = ApplicationRoute::query()
                        ->where(
                            'provider_key',
                            'recruit_agent'
                        )
                        ->where('external_id', $externalId)
                        ->first()
                        ?? ApplicationRoute::query()
                            ->where('job_posting_id', $jobPosting->id)
                            ->where('route_type', 'agent')
                            ->where('agency_id', self::AGENCY_ID)
                            ->first();

                    if (! $route) {

                        ApplicationRoute::create([
                            'job_posting_id' => $jobPosting->id,

                            'route_type' => 'agent',

                            'agency_id' => self::AGENCY_ID,

                            'platform_id' => null,

                            'application_url' => $sourceUrl,

                            'availability_status' => 'available',

                            'notes' => 'Recruit Agent public job',
                            'provider_key' => 'recruit_agent',
                            'external_id' => $externalId,
                            'first_seen_at' => now(),
                            'last_seen_at' => now(),
                            'unavailable_at' => null,
                        ]);

                        $routesCreated++;
                    } else {

                        $route->update([
                            'job_posting_id' => $jobPosting->id,
                            'route_type' => 'agent',
                            'agency_id' => self::AGENCY_ID,
                            'platform_id' => null,
                            'application_url' => $sourceUrl,

                            'availability_status' => 'available',

                            'notes' => 'Recruit Agent public job',
                            'last_seen_at' => now(),
                            'unavailable_at' => null,
                            'provider_key' => 'recruit_agent',
                            'external_id' => $externalId,
                        ]);

                        $routesUpdated++;
                    }
                });

                /*
                |--------------------------------------------------------------------------
                | 進捗表示
                |--------------------------------------------------------------------------
                */

                if (
                    $number === 1
                    || $number % 50 === 0
                    || $number === count($jobs)
                ) {
                    $this->line(
                        sprintf(
                            '[%d/%d] imported',
                            $number,
                            count($jobs)
                        )
                    );
                }
            } catch (Throwable $e) {

                $errors++;

                $this->error(
                    sprintf(
                        '[%d/%d] ERROR: %s',
                        $number,
                        count($jobs),
                        $e->getMessage()
                    )
                );
            }
        }

        if (
            ! $isPartialImport
            && ($data['completed'] ?? false) === true
        ) {
            $seenIds = collect($jobs)
                ->map(
                    fn (array $row) => $this->cleanString(
                        $row['external_id']
                            ?? $row['id']
                            ?? $row['source_url']
                            ?? null
                    )
                )
                ->filter()
                ->values();

            ApplicationRoute::query()
                ->where('provider_key', 'recruit_agent')
                ->where('availability_status', 'available')
                ->when(
                    $seenIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('external_id', $seenIds)
                )
                ->update([
                    'availability_status' => 'unavailable',
                    'unavailable_at' => now(),
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 結果
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->info(
            '=============================='
        );

        $this->info(
            '=== Recruit Agent import completed ==='
        );

        $this->line(
            'companies created: '
                .$companiesCreated
        );

        $this->line(
            'jobs created: '
                .$jobsCreated
        );

        $this->line(
            'jobs updated/existing: '
                .$jobsUpdated
        );

        $this->line(
            'routes created: '
                .$routesCreated
        );

        $this->line(
            'routes updated: '
                .$routesUpdated
        );

        $this->line(
            'skipped: '
                .$skipped
        );

        $this->line(
            'errors: '
                .$errors
        );

        $this->info(
            '=============================='
        );

        /*
        |--------------------------------------------------------------------------
        | 終了コード
        |--------------------------------------------------------------------------
        */

        if ($errors > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 文字列の軽い正規化
     */
    private function cleanString(
        mixed $value
    ): ?string {

        if (
            $value === null
            || is_array($value)
            || is_object($value)
        ) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * JobDDでは万円単位の整数で保持。
     */
    private function normalizeSalary(
        mixed $value
    ): ?int {

        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        /*
         * 0万円は「未確認」と同じ扱い。
         */
        if ($value <= 0) {
            return null;
        }

        return $value;
    }
}
