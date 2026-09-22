<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_structured_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('design_target')->nullable();
            $table->text('product_context')->nullable();
            $table->json('design_phases')->nullable();
            $table->text('initial_assignment')->nullable();
            $table->text('future_scope')->nullable();
            $table->text('required_experience')->nullable();
            $table->text('preferred_experience')->nullable();
            $table->json('collaborators')->nullable();
            $table->string('customer_contact_frequency')->nullable();
            $table->text('customer_contact_note')->nullable();
            $table->string('manufacturing_relation_frequency')->nullable();
            $table->text('manufacturing_relation_note')->nullable();
            $table->string('site_relation_frequency')->nullable();
            $table->text('site_relation_note')->nullable();
            $table->text('work_style')->nullable();
            $table->text('project_duration')->nullable();
            $table->text('concurrent_projects')->nullable();
            $table->text('difficult_points')->nullable();
            $table->text('onboarding_challenges')->nullable();
            $table->text('fit_work_style')->nullable();
            $table->text('misfit_work_style')->nullable();
            $table->json('representative_project')->nullable();
            $table->text('hard_to_convey')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_structured_profiles');
    }
};
