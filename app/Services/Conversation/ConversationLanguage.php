<?php

namespace App\Services\Conversation;

use InvalidArgumentException;

final class ConversationLanguage
{
    public const ENGLISH = 'en';

    public const KISWAHILI = 'sw';

    /**
     * Resolve a saved language preference.
     *
     * Return null when no valid preference exists, so the
     * conversation can ask the user to choose a language.
     */
    public function resolve(?string $preference): ?string
    {
        if ($preference === null) {
            return null;
        }

        $language = strtolower(trim($preference));

        return match ($language) {
            self::ENGLISH, 'english' => self::ENGLISH,
            self::KISWAHILI, 'kiswahili', 'swahili' => self::KISWAHILI,
            default => null,
        };
    }

    /**
     * Apply an explicitly classified language-change request.
     *
     * The caller must identify the requested language first.
     */
    public function changeTo(string $requestedLanguage): string
    {
        $language = $this->resolve($requestedLanguage);

        if ($language === null) {
            throw new InvalidArgumentException(
                'Unsupported conversation language.'
            );
        }

        return $language;
    }

    /**
     * Return the supported conversation language codes.
     *
     * CV documents remain English regardless of this preference.
     */
    public function supportedLanguages(): array
    {
        return [
            self::ENGLISH,
            self::KISWAHILI,
        ];
    }
}
