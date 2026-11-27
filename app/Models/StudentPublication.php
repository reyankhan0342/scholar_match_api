<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPublication extends Model
{
    protected $table = 'student_publications';

    protected $fillable = [
        'title',
        'publication_type',
        'journal_or_conference',
        'publisher',
        'authors',
        'abstract',
        'keywords',
        'research_field',
        'doi',
        'publication_url',
        'publication_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'keywords' => 'array',
            'publication_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
