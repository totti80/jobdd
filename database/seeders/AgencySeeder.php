<?php

namespace Database\Seeders;

use App\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Agency::create([
            'name' => 'A社',
            'website_url' => null,
            'occupation' => '機械設計',
            'region' => '兵庫県',
            'experience_min' => 5,
            'experience_max' => 20,
            'salary_min' => 500,
            'salary_max' => 800,
            'job_count' => 520,
            'evidence_level' => 'high',
        ]);

        Agency::create([
            'name' => 'B社',
            'website_url' => null,
            'occupation' => '機械設計',
            'region' => '大阪府',
            'experience_min' => 3,
            'experience_max' => 15,
            'salary_min' => 450,
            'salary_max' => 700,
            'job_count' => 360,
            'evidence_level' => 'medium',
        ]);

        Agency::create([
            'name' => 'C社',
            'website_url' => null,
            'occupation' => 'CADオペレーター',
            'region' => '兵庫県',
            'experience_min' => 0,
            'experience_max' => 10,
            'salary_min' => 350,
            'salary_max' => 600,
            'job_count' => 240,
            'evidence_level' => 'medium',
        ]);
    }
}
