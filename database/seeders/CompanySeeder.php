<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'name' => 'A製作所',
            'website_url' => 'https://example.com/company-a',
            'industry' => '製造業',
            'region' => '兵庫県',
        ]);
    }
}