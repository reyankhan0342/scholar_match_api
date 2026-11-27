<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_educations', function (Blueprint $table) {
            $table->id();

            // Student relationship
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Qualification details
            $table->string('qualification_level', 100);
            $table->string('qualification_full_name', 255)->nullable();
            $table->string('degree_title', 255)->nullable();
            $table->string('field_of_study', 200)->nullable();
            $table->string('specialization', 200)->nullable();

            // Institution details
            $table->string('institution_name', 255);
            $table->string('awarding_body', 255)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();

            // Academic duration and status
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('completion_year')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('degree_status', 50)->nullable();

            // Academic results
            $table->string('grading_system', 50)->nullable();
            $table->decimal('cgpa', 5, 2)->nullable();
            $table->decimal('cgpa_scale', 5, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();

            $table->unsignedInteger('obtained_marks')->nullable();
            $table->unsignedInteger('total_marks')->nullable();
            $table->string('grade', 50)->nullable();

            // Academic documents
            $table->string('degree_file_path')->nullable();
            $table->string('transcript_file_path')->nullable();

            // Additional information
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'qualification_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_educations');
    }
};
