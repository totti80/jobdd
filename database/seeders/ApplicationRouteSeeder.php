<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\ApplicationRoute;
use App\Models\JobPosting;
use App\Models\Platform;
use Illuminate\Database\Seeder;

class ApplicationRouteSeeder extends Seeder
{
    public function run(): void
    {
        $jobPosting = JobPosting::where(
            'title',
            '発電プラントの機械設計'
        )->firstOrFail();

        $agency = Agency::where('name', 'A社')->firstOrFail();

        $platform = Platform::where(
            'name',
            'B転職プラットフォーム'
        )->firstOrFail();

        ApplicationRoute::create([
            'job_posting_id' => $jobPosting->id,
            'route_type' => 'direct',
            'agency_id' => null,
            'platform_id' => null,
            'application_url' => 'https://example.com/company-a/jobs/1/apply',
            'availability_status' => 'available',
            'notes' => '企業公式採用ページから直接応募できます。',
        ]);

        ApplicationRoute::create([
            'job_posting_id' => $jobPosting->id,
            'route_type' => 'agent',
            'agency_id' => $agency->id,
            'platform_id' => null,
            'application_url' => null,
            'availability_status' => 'available',
            'notes' => 'A社による転職支援を受けながら応募できます。',
        ]);

        ApplicationRoute::create([
            'job_posting_id' => $jobPosting->id,
            'route_type' => 'platform',
            'agency_id' => null,
            'platform_id' => $platform->id,
            'application_url' => 'https://example.com/platform-b/jobs/1',
            'availability_status' => 'available',
            'notes' => '転職プラットフォーム経由で応募できます。',
        ]);
    }
}
