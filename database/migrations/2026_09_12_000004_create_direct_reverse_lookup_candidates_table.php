<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('direct_reverse_lookup_candidates')) {
      $hasIdentityIndex = DB::table('information_schema.statistics')
        ->where('table_schema', DB::connection()->getDatabaseName())
        ->where('table_name', 'direct_reverse_lookup_candidates')
        ->where('index_name', 'direct_lookup_identity_unique')
        ->exists();

      if (!$hasIdentityIndex) {
        Schema::table('direct_reverse_lookup_candidates', function (Blueprint $table) {
          $table->unique(
            ['company_id', 'region', 'occupation', 'discovery_source'],
            'direct_lookup_identity_unique'
          );
        });
      }

      return;
    }

    Schema::create('direct_reverse_lookup_candidates', function (Blueprint $table) {
      $table->id();
      $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
      $table->string('region')->nullable();
      $table->string('occupation');
      $table->string('discovery_source');
      $table->unsignedInteger('matching_job_count')->default(0);
      $table->string('website_url')->nullable();
      $table->string('direct_status')->default('unverified');
      $table->string('official_recruit_url')->nullable();
      $table->timestamp('checked_at')->nullable();
      $table->timestamp('last_seen_at')->nullable();
      $table->timestamps();
      $table->unique(
        ['company_id', 'region', 'occupation', 'discovery_source'],
        'direct_lookup_identity_unique'
      );
      $table->index(['direct_status', 'region', 'occupation'], 'direct_lookup_status_index');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('direct_reverse_lookup_candidates');
  }
};
