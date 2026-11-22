<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvPersonalDetail extends Model
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
        'email',
        'phone',
        'location',
        'date_of_birth',
        'gender',
        'marital_status',
        'nationality',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Get the CV this personal detail record belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }
}