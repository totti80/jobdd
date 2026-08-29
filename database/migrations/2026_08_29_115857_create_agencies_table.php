<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('website_url')->nullable();

            $table->string('occupation')->nullable();
            $table->string('region')->nullable();

            $table->unsignedInteger('experience_min')->nullable();
            $table->unsignedInteger('experience_max')->nullable();

            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();

            $table->unsignedInteger('job_count')->default(0);

            $table->string('evidence_level')->default('medium');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
