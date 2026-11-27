<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentResearchProfile extends Model
{
    protected $table = 'student_research_profiles';

    protected $fillable = [
        'research_title',
        'research_summary',
        'research_interests',
        'research_keywords',
        'preferred_research_fields',
        'research_experience',
        'research_goals',
        'preferred_methodology',
    ];

    protected function casts(): array
    {
        return [
            'research_interests' => 'array',
            'research_keywords' => 'array',
            'preferred_research_fields' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
