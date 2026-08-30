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
        Schema::create('interaction_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_query_id')
                ->nullable()
                ->constrained('user_queries')
                ->nullOnDelete();

            $table->string('event_type');

            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamp('occurred_at')->useCurrent();

            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interaction_logs');
    }
};
