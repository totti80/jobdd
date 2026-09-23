<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);

test('review and application requirements migrations preserve legacy data through rollback and reapply', function () {
    $migrations = array_map(fn ($path) => require $path, [
        database_path('migrations/2026_09_23_000009_add_review_state_to_job_postings_table.php'),
        database_path('migrations/2026_09_23_000010_add_application_requirements_to_job_postings_table.php'),
    ]);
    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }
    $company = DB::table('companies')->insertGetId(['name' => '既存企業']);
    $id = DB::table('job_postings')->insertGetId(['company_id' => $company, 'title' => '既存求人', 'published_at' => '2026-09-01 00:00:00']);
    $columns = Schema::getColumnListing('job_postings');
    $before = DB::table('job_postings')->orderBy('id')->get($columns)->toJson();
    for ($i = 0; $i < 2; $i++) {
        foreach ($migrations as $migration) {
            $migration->up();
        }
        expect(Schema::hasColumns('job_postings', ['review_status', 'review_requested_at', 'reviewed_at', 'reviewed_by_user_id', 'review_note', 'application_requirements']))->toBeTrue()
            ->and(DB::table('job_postings')->where('id', $id)->value('status'))->toBe('published')
            ->and(DB::table('job_postings')->where('id', $id)->value('review_status'))->toBe('not_submitted')
            ->and(DB::table('job_postings')->orderBy('id')->get($columns)->toJson())->toBe($before);
        if ($i === 0) {
            foreach (array_reverse($migrations) as $migration) {
                $migration->down();
            }
            expect(Schema::hasColumn('job_postings', 'review_status'))->toBeFalse()
                ->and(Schema::hasColumn('job_postings', 'application_requirements'))->toBeFalse()
                ->and(DB::table('job_postings')->orderBy('id')->get($columns)->toJson())->toBe($before);
        }
    }
});
