<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('job_postings', function (Blueprint $table) {
      $table->timestamp('published_at')->nullable();
      $table->timestamp('provider_updated_at')->nullable();
    });
  }

  public function down(): void
  {
    Schema::table('job_postings', function (Blueprint $table) {
      $table->dropColumn(['published_at', 'provider_updated_at']);
    });
  }
};
