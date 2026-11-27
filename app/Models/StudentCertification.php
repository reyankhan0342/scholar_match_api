<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCertification extends Model
{
    protected $table = 'student_certifications';

    protected $fillable = [
        'title',
        'issuing_organization',
        'credential_id',
        'credential_url',
        'issue_date',
        'expiry_date',
        'does_not_expire',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'does_not_expire' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
