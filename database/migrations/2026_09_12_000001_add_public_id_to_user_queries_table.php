<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('user_queries', function (Blueprint $table) {
      $table->uuid('public_id')->nullable()->unique()->after('id');
      $table->string('session_token', 128)->nullable()->index()->after('public_id');
    });
  }

  public function down(): void
  {
    Schema::table('user_queries', function (Blueprint $table) {
      $table->dropUnique(['public_id']);
      $table->dropIndex(['session_token']);
      $table->dropColumn(['public_id', 'session_token']);
    });
  }
};
