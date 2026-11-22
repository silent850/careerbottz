<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvSkill extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cv_id',
        'skill_name',
        'category',
    ];

    /**
     * Get the CV this skill belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }
}