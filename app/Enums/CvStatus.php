<?php

namespace App\Enums;

enum CvStatus: string
{
    case DRAFT = 'draft';
    case REVIEWING = 'reviewing';
    case CONFIRMED = 'confirmed';
    case GENERATING = 'generating';
    case COMPLETED = 'completed';
}