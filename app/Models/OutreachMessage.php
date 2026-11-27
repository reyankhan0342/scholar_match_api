<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutreachMessage extends Model
{
    protected $fillable = [
        'user_id', 'professor_name', 'professor_email', 'university_name',
        'subject', 'body', 'attach_cv', 'status', 'gmail_message_id', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['attach_cv' => 'boolean', 'sent_at' => 'datetime'];
    }
}
