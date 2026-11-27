<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEducation extends Model
{
    protected $table = 'student_educations';

    protected $fillable = [
        'qualification_level',
        'qualification_full_name',
        'degree_title',
        'field_of_study',
        'specialization',
        'institution_name',
        'awarding_body',
        'country',
        'city',
        'start_year',
        'completion_year',
        'is_current',
        'degree_status',
        'grading_system',
        'cgpa',
        'cgpa_scale',
        'percentage',
        'obtained_marks',
        'total_marks',
        'grade',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'completion_year' => 'integer',
            'is_current' => 'boolean',
            'cgpa' => 'decimal:2',
            'cgpa_scale' => 'decimal:2',
            'percentage' => 'decimal:2',
            'obtained_marks' => 'integer',
            'total_marks' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
