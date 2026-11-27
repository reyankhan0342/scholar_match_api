<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_certifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->string('issuing_organization');

            $table->string('credential_id')->nullable();
            $table->string('credential_url', 500)->nullable();

            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->boolean('does_not_expire')->default(false);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_certifications');
    }
};
