<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->text('eyecatch_image_url')->nullable()->default(null);
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', fn (Blueprint $table) => $table->dropColumn('eyecatch_image_url'));
    }
};
