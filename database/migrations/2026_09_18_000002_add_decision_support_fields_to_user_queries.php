<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('user_queries', function (Blueprint $table) {
      $table->json('detailed_skills')->nullable();
      $table->json('priorities')->nullable();
    });
  }

  public function down(): void
  {
    Schema::table('user_queries', function (Blueprint $table) {
      $table->dropColumn(['detailed_skills', 'priorities']);
    });
  }
};
