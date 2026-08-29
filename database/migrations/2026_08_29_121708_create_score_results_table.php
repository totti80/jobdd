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
        Schema::create('score_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_query_id')
                ->constrained('user_queries')
                ->cascadeOnDelete();

            $table->foreignId('agency_id')
                ->constrained('agencies')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('score');

            $table->unsignedTinyInteger('occupation_score')->nullable();
            $table->unsignedTinyInteger('region_score')->nullable();
            $table->unsignedTinyInteger('experience_score')->nullable();
            $table->unsignedTinyInteger('salary_score')->nullable();
            $table->unsignedTinyInteger('job_score')->nullable();

            $table->text('reason')->nullable();

            $table->string('score_model_version')->default('v0.1');

            $table->timestamps();

            $table->unique(
                ['user_query_id', 'agency_id', 'score_model_version'],
                'score_results_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_results');
    }
};
