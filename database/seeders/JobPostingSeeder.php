<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Database\Seeder;

class JobPostingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'A製作所')->firstOrFail();

        JobPosting::create([
            'company_id' => $company->id,
            'title' => '発電プラントの機械設計',
            'occupation' => '機械設計',
            'industry' => '製造業',
            'region' => '兵庫県',
            'salary_min' => 500,
            'salary_max' => 800,
            'description' => '発電プラント設備の機械設計業務です。',
            'employment_type' => '正社員',
            'source_url' => 'https://example.com/company-a/jobs/1',
        ]);
    }
}
