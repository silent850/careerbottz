<?php

namespace Tests\Unit\Services\Conversation;

use App\Services\Conversation\ConversationState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConversationStateTest extends TestCase
{
    public static function approvedStates(): array
    {
        return [
            ['welcome', ConversationState::WELCOME],
            ['language_selection', ConversationState::LANGUAGE_SELECTION],
            ['cv_selection', ConversationState::CV_SELECTION],
            ['cv_naming', ConversationState::CV_NAMING],
            ['personal_details', ConversationState::PERSONAL_DETAILS],
            ['education', ConversationState::EDUCATION],
            ['experience', ConversationState::EXPERIENCE],
            ['skills', ConversationState::SKILLS],
            ['projects', ConversationState::PROJECTS],
            ['certifications', ConversationState::CERTIFICATIONS],
            ['languages', ConversationState::LANGUAGES],
            ['additional_sections', ConversationState::ADDITIONAL_SECTIONS],
            ['content_review', ConversationState::CONTENT_REVIEW],
            ['cv_preview', ConversationState::CV_PREVIEW],
            ['awaiting_confirmation', ConversationState::AWAITING_CONFIRMATION],
            ['generating_document', ConversationState::GENERATING_DOCUMENT],
            ['completed', ConversationState::COMPLETED],
        ];
    }

    #[DataProvider('approvedStates')]
    public function test_state_values_match_the_approved_design(
        string $expectedValue,
        ConversationState $state,
    ): void {
        $this->assertSame($expectedValue, $state->value);
        $this->assertSame($state, ConversationState::from($expectedValue));
    }

    public function test_unknown_state_value_is_rejected(): void
    {
        $this->expectException(\ValueError::class);

        ConversationState::from('unknown_state');
    }
}
