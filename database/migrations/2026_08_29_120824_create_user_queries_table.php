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
        Schema::create('user_queries', function (Blueprint $table) {
            $table->id();

            $table->text('raw_text');

            $table->string('occupation')->nullable();
            $table->string('industry')->nullable();
            $table->string('region')->nullable();

            $table->unsignedInteger('experience_years')->nullable();

            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();

            $table->string('validation_domain')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_queries');
    }
};
