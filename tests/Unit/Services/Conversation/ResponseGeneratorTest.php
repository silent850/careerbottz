<?php

namespace Tests\Unit\Services\Conversation;

use App\Services\AI\AIProvider;
use App\Services\Conversation\ConversationLanguage;
use App\Services\Conversation\ResponseGenerator;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ResponseGeneratorTest extends TestCase
{
    public function test_it_generates_a_response_using_the_selected_language(): void
    {
        $provider = Mockery::mock(AIProvider::class);

        $provider->shouldReceive('generateConversationalResponse')
            ->once()
            ->withArgs(function (
                string $message,
                array $context,
            ): bool {
                return $message === 'Habari, naweza kupata msaada?'
                    && $context['language'] === 'sw'
                    && $context['current_state'] === 'welcome';
            })
            ->andReturn('Karibu! Nitakusaidia.');

        $generator = new ResponseGenerator(
            $provider,
            new ConversationLanguage(),
        );

        $result = $generator->generate(
            'Habari, naweza kupata msaada?',
            [
                'language' => 'sw',
                'current_state' => 'welcome',
            ],
        );

        $this->assertSame('Karibu! Nitakusaidia.', $result);
    }

    public function test_it_uses_english_when_language_is_missing(): void
    {
        $provider = Mockery::mock(AIProvider::class);

        $provider->shouldReceive('generateConversationalResponse')
            ->once()
            ->withArgs(function (
                string $message,
                array $context,
            ): bool {
                return $context['language'] === 'en';
            })
            ->andReturn('Hello! How can I help you?');

        $generator = new ResponseGenerator(
            $provider,
            new ConversationLanguage(),
        );

        $result = $generator->generate('Hello');

        $this->assertSame(
            'Hello! How can I help you?',
            $result,
        );
    }

    public function test_it_returns_a_fallback_for_an_empty_message(): void
    {
        $provider = Mockery::mock(AIProvider::class);

        $provider->shouldNotReceive(
            'generateConversationalResponse',
        );

        $generator = new ResponseGenerator(
            $provider,
            new ConversationLanguage(),
        );

        $result = $generator->generate(
            '   ',
            ['language' => 'sw'],
        );

        $this->assertSame(
            'Samahani, kwa sasa siwezi kujibu vizuri. Tafadhali jaribu tena baada ya muda mfupi.',
            $result,
        );
    }

    public function test_it_returns_a_localized_fallback_when_gemini_fails(): void
    {
        $provider = Mockery::mock(AIProvider::class);

        $provider->shouldReceive('generateConversationalResponse')
            ->once()
            ->andThrow(new RuntimeException('Simulated Gemini failure.'));

        $generator = new ResponseGenerator(
            $provider,
            new ConversationLanguage(),
        );

        $result = $generator->generate(
            'Hello',
            ['language' => 'en'],
        );

        $this->assertSame(
            'Sorry, I cannot respond properly right now. Please try again shortly.',
            $result,
        );
    }

    public function test_it_returns_a_fallback_when_gemini_returns_empty_text(): void
    {
        $provider = Mockery::mock(AIProvider::class);

        $provider->shouldReceive('generateConversationalResponse')
            ->once()
            ->andReturn('   ');

        $generator = new ResponseGenerator(
            $provider,
            new ConversationLanguage(),
        );

        $result = $generator->generate(
            'Hello',
            ['language' => 'en'],
        );

        $this->assertSame(
            'Sorry, I cannot respond properly right now. Please try again shortly.',
            $result,
        );
    }

    public function test_it_limits_conversation_history_to_ten_entries(): void
    {
        $history = [];

        for ($i = 1; $i <= 15; $i++) {
            $history[] = [
                'role' => 'user',
                'content' => "Message {$i}",
            ];
        }

        $provider = Mockery::mock(AIProvider::class);

        $provider->shouldReceive('generateConversationalResponse')
            ->once()
            ->withArgs(function (
                string $message,
                array $context,
            ): bool {
                $history = $context['conversation_history'];

                return count($history) === 10
                    && $history[0]['content'] === 'Message 6'
                    && $history[9]['content'] === 'Message 15';
            })
            ->andReturn('I understand.');

        $generator = new ResponseGenerator(
            $provider,
            new ConversationLanguage(),
        );

        $result = $generator->generate(
            'Please continue.',
            [
                'language' => 'en',
                'conversation_history' => $history,
            ],
        );

        $this->assertSame('I understand.', $result);
    }
}
