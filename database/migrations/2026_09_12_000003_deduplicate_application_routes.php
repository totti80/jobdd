<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    $duplicateGroups = DB::table('application_routes')
      ->select('job_posting_id', 'route_type', 'agency_id', 'platform_id')
      ->groupBy('job_posting_id', 'route_type', 'agency_id', 'platform_id')
      ->havingRaw('COUNT(*) > 1')
      ->get();

    foreach ($duplicateGroups as $group) {
      $routes = DB::table('application_routes')
        ->where('job_posting_id', $group->job_posting_id)
        ->where('route_type', $group->route_type)
        ->where('agency_id', $group->agency_id)
        ->where('platform_id', $group->platform_id)
        ->orderByDesc('last_seen_at')
        ->orderByDesc('id')
        ->get();

      $keep = $routes->first();

      if (!$keep) {
        continue;
      }

      DB::table('application_routes')
        ->whereIn('id', $routes->skip(1)->pluck('id'))
        ->delete();
    }

    DB::statement(<<<'SQL'
            UPDATE application_routes ar
            INNER JOIN job_postings jp ON jp.id = ar.job_posting_id
            SET
                ar.provider_key = jp.provider_key,
                ar.external_id = jp.external_id,
                ar.first_seen_at = COALESCE(ar.first_seen_at, jp.first_seen_at, ar.created_at),
                ar.last_seen_at = COALESCE(ar.last_seen_at, jp.last_seen_at, ar.updated_at)
            WHERE ar.provider_key IS NULL
              AND ar.external_id IS NULL
              AND jp.provider_key IS NOT NULL
              AND jp.external_id IS NOT NULL
        SQL);
  }

  public function down(): void
  {
    // Existing route identity values are retained on rollback.
  }
};
