<?php

namespace App\Models;

use App\Enums\CvStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cv extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'document_language',
        'status',
        'template',
        'professional_summary',
        'job_context',
    ];

    protected function casts(): array
    {
        return [
            'status' => CvStatus::class,
            'job_context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personalDetails(): HasOne
    {
        return $this->hasOne(CvPersonalDetail::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(CvEducation::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(CvExperience::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CvSkill::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(CvProject::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(CvCertification::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(CvLanguage::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(CvReference::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CvSection::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CvVersion::class);
    }
}