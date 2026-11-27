<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_publications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->string('publication_type', 100)->nullable();
            $table->string('journal_or_conference')->nullable();
            $table->string('publisher')->nullable();

            $table->json('authors')->nullable();
            $table->text('abstract')->nullable();
            $table->json('keywords')->nullable();

            $table->string('research_field')->nullable();
            $table->string('doi', 255)->nullable();
            $table->string('publication_url', 500)->nullable();

            $table->date('publication_date')->nullable();
            $table->string('status', 50)->default('draft');

            $table->timestamps();

            $table->index(['user_id', 'publication_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_publications');
    }
};
