<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmailConnection extends Model
{
    protected $fillable = ['user_id', 'email', 'refresh_token'];
    protected $hidden = ['refresh_token'];
    protected function casts(): array
    {
        return ['refresh_token' => 'encrypted'];
    }
}
