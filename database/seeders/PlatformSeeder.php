<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        Platform::create([
            'name' => 'B転職プラットフォーム',
            'website_url' => 'https://example.com/platform-b',
            'platform_type' => '転職プラットフォーム',
        ]);
    }
}