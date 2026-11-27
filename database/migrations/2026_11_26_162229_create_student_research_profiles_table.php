<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_research_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('research_title')->nullable();
            $table->text('research_summary')->nullable();

            $table->json('research_interests')->nullable();
            $table->json('research_keywords')->nullable();
            $table->json('preferred_research_fields')->nullable();

            $table->text('research_experience')->nullable();
            $table->text('research_goals')->nullable();
            $table->text('preferred_methodology')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_research_profiles');
    }
};
