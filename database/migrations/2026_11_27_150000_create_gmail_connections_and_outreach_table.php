<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gmail_oauth_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('state_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('gmail_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->text('refresh_token');
            $table->timestamps();
        });

        Schema::create('outreach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('professor_name')->nullable();
            $table->string('professor_email');
            $table->string('university_name')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->boolean('attach_cv')->default(false);
            $table->string('status', 20)->default('draft');
            $table->string('gmail_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_messages');
        Schema::dropIfExists('gmail_connections');
        Schema::dropIfExists('gmail_oauth_states');
    }
};
