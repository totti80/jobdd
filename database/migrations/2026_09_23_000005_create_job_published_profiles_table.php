<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_published_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('profile_data');
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_published_profiles');
    }
};
