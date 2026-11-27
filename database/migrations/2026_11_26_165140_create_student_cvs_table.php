<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_cvs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('original_file_name', 255);

            $table->string('file_path', 500);

            $table->string('mime_type', 100);

            $table->unsignedBigInteger('file_size');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_cvs');
    }
};
