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
        Schema::create('application_routes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_posting_id')
                ->constrained('job_postings')
                ->cascadeOnDelete();

            $table->string('route_type');

            $table->foreignId('agency_id')
                ->nullable()
                ->constrained('agencies')
                ->nullOnDelete();

            $table->foreignId('platform_id')
                ->nullable()
                ->constrained('platforms')
                ->nullOnDelete();

            $table->string('application_url')->nullable();

            $table->string('availability_status')
                ->default('available');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['job_posting_id', 'route_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_routes');
    }
};
