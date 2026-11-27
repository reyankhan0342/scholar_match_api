<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_research_proposals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('abstract')->nullable();
            $table->string('research_field')->nullable();
            $table->json('keywords')->nullable();

            $table->text('research_objectives')->nullable();
            $table->text('research_methodology')->nullable();
            $table->text('expected_outcomes')->nullable();

            $table->string('target_degree', 100)->nullable();

            $table->string('document_file_path')->nullable();
            $table->string('original_file_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->string('status', 30)->default('draft');

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_research_proposals');
    }
};
