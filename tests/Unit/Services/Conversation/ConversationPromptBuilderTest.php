<?php

namespace Tests\Unit\Services\Conversation;

use App\Services\Conversation\ConversationLanguage;
use App\Services\Conversation\ConversationPromptBuilder;
use App\Services\Conversation\ConversationState;
use PHPUnit\Framework\TestCase;

class ConversationPromptBuilderTest extends TestCase
{
    private ConversationPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new ConversationPromptBuilder();
    }

    public function test_it_returns_the_welcome_prompt_in_english(): void
    {
        $prompt = $this->builder->forState(
            ConversationState::WELCOME,
            ConversationLanguage::ENGLISH,
        );

        $this->assertSame(
            'Welcome to CareerBotTz! Send "help" for options or "new CV" to start a CV.',
            $prompt,
        );
    }

    public function test_it_returns_the_welcome_prompt_in_kiswahili(): void
    {
        $prompt = $this->builder->forState(
            ConversationState::WELCOME,
            ConversationLanguage::KISWAHILI,
        );

        $this->assertSame(
            'Karibu CareerBotTz! Andika "help" kuona chaguo au "new CV" kuanza CV.',
            $prompt,
        );
    }

    public function test_it_returns_the_correct_prompt_for_each_language(): void
    {
        $english = $this->builder->forState(
            ConversationState::PERSONAL_DETAILS,
            ConversationLanguage::ENGLISH,
        );

        $kiswahili = $this->builder->forState(
            ConversationState::PERSONAL_DETAILS,
            ConversationLanguage::KISWAHILI,
        );

        $this->assertStringContainsString('personal details', $english);
        $this->assertStringContainsString('taarifa zako za msingi', $kiswahili);
        $this->assertNotSame($english, $kiswahili);
    }

    public function test_it_returns_a_prompt_for_every_conversation_state(): void
    {
        foreach (ConversationState::cases() as $state) {
            $english = $this->builder->forState(
                $state,
                ConversationLanguage::ENGLISH,
            );

            $kiswahili = $this->builder->forState(
                $state,
                ConversationLanguage::KISWAHILI,
            );

            $this->assertNotEmpty($english, "English prompt is empty for {$state->value}");
            $this->assertNotEmpty($kiswahili, "Kiswahili prompt is empty for {$state->value}");
        }
    }
}
