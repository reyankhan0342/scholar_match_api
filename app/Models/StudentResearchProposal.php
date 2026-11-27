<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentResearchProposal extends Model
{
    protected $table = 'student_research_proposals';

    protected $fillable = [
        'title',
        'abstract',
        'research_field',
        'keywords',
        'research_objectives',
        'research_methodology',
        'expected_outcomes',
        'target_degree',
        'status',
    ];

    protected $hidden = [
        'document_file_path',
    ];

    protected $appends = [
        'has_document',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'file_size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getHasDocumentAttribute(): bool
    {
        return !empty($this->document_file_path);
    }
}
