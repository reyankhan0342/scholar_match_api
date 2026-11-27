<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProject extends Model
{
    protected $table = 'student_projects';

    protected $fillable = [
        'title',
        'project_type',
        'role',
        'description',
        'research_field',
        'technologies',
        'project_url',
        'repository_url',
        'start_date',
        'end_date',
        'is_ongoing',
    ];

    protected function casts(): array
    {
        return [
            'technologies' => 'array',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_ongoing' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
