<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('job_facts', function (Blueprint $table) {
      $table->id();

      $table->foreignId('job_posting_id')
        ->constrained('job_postings')
        ->cascadeOnDelete();

      $table->foreignId('source_id')
        ->nullable()
        ->constrained('sources')
        ->nullOnDelete();

      $table->string('fact_category');
      $table->string('fact_key');
      $table->text('fact_value');
      $table->text('normalized_value')->nullable();
      $table->string('extraction_method');
      $table->string('verification_status');
      $table->text('evidence_text')->nullable();
      $table->timestamp('observed_at')->nullable();
      $table->timestamps();

      $table->index(
        ['job_posting_id', 'fact_category', 'fact_key'],
        'job_facts_posting_category_key_index'
      );
      $table->index('verification_status');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('job_facts');
  }
};
