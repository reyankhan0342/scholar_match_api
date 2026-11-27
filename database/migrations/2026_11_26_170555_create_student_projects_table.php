<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_projects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->string('project_type', 100)->nullable();
            $table->string('role', 150)->nullable();

            $table->text('description')->nullable();
            $table->string('research_field')->nullable();

            $table->json('technologies')->nullable();

            $table->string('project_url', 500)->nullable();
            $table->string('repository_url', 500)->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_ongoing')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_projects');
    }
};
