<?php

namespace App\Services\Conversation;

use App\Services\AI\AIProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResponseGenerator implements ResponseGeneratorContract
{
    private const MESSAGE_LIMIT = 2000;
    private const HISTORY_LIMIT = 10;
    private const PENDING_QUESTION_LIMIT = 500;
    private const RESPONSE_LIMIT = 2000;

    public function __construct(
        private readonly AIProvider $aiProvider,
        private readonly ConversationLanguage $languageResolver,
    ) {
    }

    /**
     * Generate a natural response using the supplied conversation context.
     *
     * @param array<string, mixed> $context
     */
    public function generate(
        string $message,
        array $context = [],
    ): string {
        $language = $this->languageResolver->resolve(
            is_string($context['language'] ?? null)
                ? $context['language']
                : null,
        ) ?? ConversationLanguage::ENGLISH;

        $message = trim(mb_substr(
            $message,
            0,
            self::MESSAGE_LIMIT,
        ));

        if ($message === '') {
            return $this->fallback($language);
        }

        $safeContext = $this->normalizeContext(
            $context,
            $language,
        );

        try {
            $response = $this->aiProvider
                ->generateConversationalResponse(
                    $message,
                    $safeContext,
                );

            $response = trim($response);

            if ($response === '') {
                return $this->fallback($language);
            }

            return mb_substr(
                $response,
                0,
                self::RESPONSE_LIMIT,
            );
        } catch (Throwable $exception) {
           Log::warning(
    'CareerBotTz conversational response generation failed.',
    [
        'exception' => $exception::class,
        'error' => $exception->getMessage(),
        'previous_exception' => $exception->getPrevious()?->getMessage(),
        'language' => $language,
    ],
);

            return $this->fallback($language);
        }
    }

    /**
     * Keep only the bounded context needed for response generation.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeContext(
        array $context,
        string $language,
    ): array {
        $history = [];
        $rawHistory = $context['conversation_history'] ?? [];

        if (is_array($rawHistory)) {
            foreach (
                array_slice($rawHistory, -self::HISTORY_LIMIT)
                as $entry
            ) {
                if (! is_array($entry)) {
                    continue;
                }

                $role = $entry['role'] ?? null;
                $content = $entry['content'] ?? null;

                if (
                    ! in_array($role, ['user', 'assistant'], true)
                    || ! is_string($content)
                    || trim($content) === ''
                ) {
                    continue;
                }

                $historyEntry = [
                    'role' => $role,
                    'content' => mb_substr(
                        trim($content),
                        0,
                        self::MESSAGE_LIMIT,
                    ),
                ];

                if (is_string($entry['timestamp'] ?? null)) {
                    $historyEntry['timestamp'] = mb_substr(
                        $entry['timestamp'],
                        0,
                        40,
                    );
                }

                $history[] = $historyEntry;
            }
        }

        return [
            'conversation_id' => is_int($context['conversation_id'] ?? null)
                ? $context['conversation_id']
                : null,

            'current_state' => $this->safeString(
                $context['current_state'] ?? null,
                100,
            ),

            'previous_state' => $this->safeString(
                $context['previous_state'] ?? null,
                100,
            ),

            'language' => $language,

            'intent' => $this->safeString(
                $context['intent'] ?? null,
                100,
            ),

            'requires_clarification' => is_bool(
                $context['requires_clarification'] ?? null
            )
                ? $context['requires_clarification']
                : false,

            'pending_question' => $this->safeString(
                $context['pending_question'] ?? null,
                self::PENDING_QUESTION_LIMIT,
            ),

            'conversation_history' => $history,
        ];
    }

    private function safeString(
        mixed $value,
        int $limit,
    ): ?string {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, $limit);
    }

    private function fallback(string $language): string
    {
        return $language === ConversationLanguage::KISWAHILI
            ? 'Samahani, kwa sasa siwezi kujibu vizuri. Tafadhali jaribu tena baada ya muda mfupi.'
            : 'Sorry, I cannot respond properly right now. Please try again shortly.';
    }
}