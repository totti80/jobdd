<?php

namespace App\Console\Commands;

use App\Services\DirectLookup\OfficialPageDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DiscoverDirectOfficialPages extends Command
{
    protected $signature = 'jobdd:discover-direct-official-pages
        {--input=storage/app/private/crawler/direct_lookup_queue.json}
        {--output=storage/app/private/crawler/direct_lookup_discovery_review.json}
        {--limit=5}';

    protected $description = 'Discover page candidates for human review without updating Direct evidence.';

    public function handle(OfficialPageDiscovery $discovery): int
    {
        try {
            $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
            if ($limit === false) {
                throw new \InvalidArgumentException('limit must be between 1 and 5 for this discovery slice.');
            }
            $input = $this->privatePath($this->option('input'));
            $output = $this->privatePath($this->option('output'));
            if ($input === $output) {
                throw new \InvalidArgumentException('Input and review output must be separate files.');
            }
            $records = json_decode(File::get($input), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($records) || ! array_is_list($records)) {
                throw new \InvalidArgumentException('Input must be a queue JSON array.');
            }
            foreach ($records as $record) {
                if (! is_array($record) || ! is_int($record['company_id'] ?? null) || blank($record['company_name'] ?? null) || ($record['status'] ?? null) !== 'unverified' || ! is_array($record['discovery_candidates'] ?? null)) {
                    throw new \InvalidArgumentException('Expected unverified records from BuildDirectLookupQueue.');
                }
            }
            // Merge repeated company records without dropping their source cells.
            $companies = collect($records)->groupBy('company_id')->take($limit)->map(function ($rows) {
                $record = $rows->first();
                $record['discovery_candidates'] = $rows->flatMap(fn ($row) => $row['discovery_candidates'])->unique('candidate_id')->values()->all();

                return $record;
            });
            $results = [];
            foreach ($companies as $record) {
                $this->line('Discovering candidates: '.$record['company_name']);
                $results[] = $discovery->discover($record);
            }
            File::ensureDirectoryExists(dirname($output), 0700);
            File::replace($output, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n", 0600);
            $this->info('Review companies: '.count($results));
            $this->info($output);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function privatePath(string $option): string
    {
        $path = str_starts_with($option, '/') ? $option : base_path($option);
        $root = storage_path('app/private/crawler');
        if (! str_starts_with($path, $root.'/') || str_contains(substr($path, strlen($root) + 1), '/') || ! str_ends_with($path, '.json')) {
            throw new \InvalidArgumentException('Use a JSON file directly under storage/app/private/crawler.');
        }
        // Do not follow a symlink outside the private output directory.
        for ($check = $path; $check !== dirname($check); $check = dirname($check)) {
            if (is_link($check)) {
                throw new \InvalidArgumentException('Symlink paths are not supported.');
            }
        }

        return $path;
    }
}
