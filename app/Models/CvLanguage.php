<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvLanguage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cv_id',
        'language',
        'proficiency_level',
    ];

    /**
     * Get the CV this language record belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }
}