<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_facts', function (Blueprint $table) {
            $table->string('context_role')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_facts', function (Blueprint $table) {
            $table->dropColumn('context_role');
        });
    }
};
