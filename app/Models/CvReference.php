<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvReference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cv_id',
        'full_name',
        'professional_title',
        'organization',
        'email',
        'phone',
        'relationship',
    ];

    /**
     * Get the CV this reference belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }
}