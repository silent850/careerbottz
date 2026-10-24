<?php

namespace App\Services\Conversation;

enum ConversationState: string
{
    case WELCOME = 'welcome';
    case LANGUAGE_SELECTION = 'language_selection';
    case CV_SELECTION = 'cv_selection';
    case CV_NAMING = 'cv_naming';
    case PERSONAL_DETAILS = 'personal_details';
    case EDUCATION = 'education';
    case EXPERIENCE = 'experience';
    case SKILLS = 'skills';
    case PROJECTS = 'projects';
    case CERTIFICATIONS = 'certifications';
    case LANGUAGES = 'languages';
    case ADDITIONAL_SECTIONS = 'additional_sections';
    case CONTENT_REVIEW = 'content_review';
    case CV_PREVIEW = 'cv_preview';
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';
    case GENERATING_DOCUMENT = 'generating_document';
    case COMPLETED = 'completed';
}
