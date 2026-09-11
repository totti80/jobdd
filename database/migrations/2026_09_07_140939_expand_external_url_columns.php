<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->text('source_url')->nullable()->change();
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->text('url')->change();
        });

        Schema::table('application_routes', function (Blueprint $table) {
            $table->text('application_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('source_url')->nullable()->change();
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->string('url')->change();
        });

        Schema::table('application_routes', function (Blueprint $table) {
            $table->string('application_url')->nullable()->change();
        });
    }
};