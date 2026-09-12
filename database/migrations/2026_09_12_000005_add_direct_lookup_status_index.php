<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    $hasIndex = DB::table('information_schema.statistics')
      ->where('table_schema', DB::connection()->getDatabaseName())
      ->where('table_name', 'direct_reverse_lookup_candidates')
      ->where('index_name', 'direct_lookup_status_index')
      ->exists();

    if (!$hasIndex) {
      Schema::table('direct_reverse_lookup_candidates', function (Blueprint $table) {
        $table->index(
          ['direct_status', 'region', 'occupation'],
          'direct_lookup_status_index'
        );
      });
    }
  }

  public function down(): void
  {
    Schema::table('direct_reverse_lookup_candidates', function (Blueprint $table) {
      $table->dropIndex('direct_lookup_status_index');
    });
  }
};
