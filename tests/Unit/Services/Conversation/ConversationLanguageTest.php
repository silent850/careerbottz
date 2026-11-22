<?php

namespace Tests\Unit\Services\Conversation;

use App\Services\Conversation\ConversationLanguage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConversationLanguageTest extends TestCase
{
    public function test_it_resolves_english_preferences(): void
    {
        $service = new ConversationLanguage();

        $this->assertSame('en', $service->resolve('en'));
        $this->assertSame('en', $service->resolve('English'));
        $this->assertSame('en', $service->resolve(' ENGLISH '));
    }

    public function test_it_resolves_kiswahili_preferences(): void
    {
        $service = new ConversationLanguage();

        $this->assertSame('sw', $service->resolve('sw'));
        $this->assertSame('sw', $service->resolve('Kiswahili'));
        $this->assertSame('sw', $service->resolve('Swahili'));
    }

    public function test_missing_or_unsupported_preferences_require_selection(): void
    {
        $service = new ConversationLanguage();

        $this->assertNull($service->resolve(null));
        $this->assertNull($service->resolve('French'));
    }

    public function test_it_applies_an_explicit_language_change(): void
    {
        $service = new ConversationLanguage();

        $this->assertSame('sw', $service->changeTo('Kiswahili'));
        $this->assertSame('en', $service->changeTo('English'));
    }

    public function test_it_rejects_an_unsupported_language_change(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ConversationLanguage())->changeTo('French');
    }

    public function test_only_english_and_kiswahili_are_supported(): void
    {
        $this->assertSame(
            ['en', 'sw'],
            (new ConversationLanguage())->supportedLanguages()
        );
    }
}
