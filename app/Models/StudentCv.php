<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCv extends Model
{
    protected $table = 'student_cvs';

    protected $fillable = [
        'original_file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected $hidden = [
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
