<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::create([
            'source_type' => 'official',
            'title' => 'A社 公式求人情報',
            'publisher' => 'A社',
            'url' => 'https://example.com/a',
            'fetched_at' => now(),
        ]);

        Source::create([
            'source_type' => 'official',
            'title' => 'B社 公式求人情報',
            'publisher' => 'B社',
            'url' => 'https://example.com/b',
            'fetched_at' => now(),
        ]);

        Source::create([
            'source_type' => 'official',
            'title' => 'C社 公式求人情報',
            'publisher' => 'C社',
            'url' => 'https://example.com/c',
            'fetched_at' => now(),
        ]);
    }
}
