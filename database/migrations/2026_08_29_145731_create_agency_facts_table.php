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
        Schema::create('agency_facts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agency_id')
                ->constrained('agencies')
                ->cascadeOnDelete();

            $table->foreignId('source_id')
                ->constrained('sources')
                ->cascadeOnDelete();

            $table->string('fact_type');
            $table->string('fact_key');
            $table->text('fact_value');

            $table->string('verification_status')->default('pending');

            $table->timestamp('observed_at')->nullable();

            $table->timestamps();

            $table->index(['agency_id', 'fact_key']);
            $table->index('verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agency_facts');
    }
};
