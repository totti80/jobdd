<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('job_postings', function (Blueprint $table) {
      $table->string('provider_key')->nullable()->after('source_url');
      $table->string('external_id')->nullable()->after('provider_key');
      $table->timestamp('first_seen_at')->nullable()->after('external_id');
      $table->timestamp('last_seen_at')->nullable()->after('first_seen_at');
      $table->timestamp('unavailable_at')->nullable()->after('last_seen_at');
      $table->unique(['provider_key', 'external_id']);
    });

    Schema::table('application_routes', function (Blueprint $table) {
      $table->string('provider_key')->nullable()->after('notes');
      $table->string('external_id')->nullable()->after('provider_key');
      $table->timestamp('first_seen_at')->nullable()->after('external_id');
      $table->timestamp('last_seen_at')->nullable()->after('first_seen_at');
      $table->timestamp('unavailable_at')->nullable()->after('last_seen_at');
      $table->unique(['provider_key', 'external_id']);
    });
  }

  public function down(): void
  {
    Schema::table('application_routes', function (Blueprint $table) {
      $table->dropUnique(['provider_key', 'external_id']);
      $table->dropColumn(['provider_key', 'external_id', 'first_seen_at', 'last_seen_at', 'unavailable_at']);
    });

    Schema::table('job_postings', function (Blueprint $table) {
      $table->dropUnique(['provider_key', 'external_id']);
      $table->dropColumn(['provider_key', 'external_id', 'first_seen_at', 'last_seen_at', 'unavailable_at']);
    });
  }
};
