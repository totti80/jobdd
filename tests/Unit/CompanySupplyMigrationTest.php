<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);

test('Phase A migrations preserve legacy rows through rollback and reapplication', function () {
    // DatabaseMigrations runs only against the isolated test database.
    $paths = glob(database_path('migrations/2026_09_23_00000[1-8]_*.php'));
    expect($paths)->toHaveCount(8);
    $migrations = array_map(fn ($path) => require $path, $paths);
    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }

    $user = DB::table('users')->insertGetId(['name' => '既存User', 'email' => 'legacy@example.test', 'password' => 'legacy-hash']);
    $company = DB::table('companies')->insertGetId(['name' => '既存企業']);
    $source = DB::table('sources')->insertGetId(['source_type' => 'official_site', 'url' => 'https://example.test']);
    $job = DB::table('job_postings')->insertGetId(['company_id' => $company, 'title' => '既存求人', 'published_at' => '2026-09-01 10:00:00']);
    DB::table('application_routes')->insert(['job_posting_id' => $job, 'route_type' => 'direct']);
    DB::table('job_facts')->insert(['job_posting_id' => $job, 'source_id' => $source,
        'fact_category' => 'skill', 'fact_key' => 'cad', 'fact_value' => 'AutoCAD',
        'extraction_method' => 'rule', 'verification_status' => 'unverified']);
    $tables = ['users', 'companies', 'job_postings', 'sources', 'application_routes', 'job_facts'];
    $before = [];
    $columns = [];
    foreach ($tables as $table) {
        $columns[$table] = Schema::getColumnListing($table);
        $before[$table] = DB::table($table)->orderBy('id')->get($columns[$table])->toJson();
    }

    for ($cycle = 0; $cycle < 2; $cycle++) {
        foreach ($migrations as $migration) {
            $migration->up();
        }
        expect(DB::table('job_postings')->where('id', $job)->value('status'))->toBe('published')
            ->and(DB::table('users')->where('id', $user)->value('system_role'))->toBe('user')
            ->and(DB::table('job_facts')->value('context_role'))->toBeNull();
        foreach ($tables as $table) {
            expect(DB::table($table)->orderBy('id')->get($columns[$table])->toJson())->toBe($before[$table]);
        }
        if ($cycle === 0) {
            foreach (array_reverse($migrations) as $migration) {
                $migration->down();
            }
            foreach (['company_user', 'job_structured_profiles', 'job_tool_usages', 'job_typical_day_items', 'job_published_profiles'] as $table) {
                expect(Schema::hasTable($table))->toBeFalse();
            }
            foreach (['users' => 'system_role', 'job_postings' => 'status', 'job_facts' => 'context_role'] as $table => $column) {
                expect(Schema::hasColumn($table, $column))->toBeFalse();
            }
            foreach ($tables as $table) {
                expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($before[$table]);
            }
        }
    }
});
