<?php

namespace App\Services\AI;

final class IntentClassifier implements IntentClassifierContract
{
    public const MIN_CONFIDENCE = 0.75;

    /**
     * These are interpretation categories, not executable actions.
     *
     * @var list<string>
     */
    public const ALLOWED_INTENTS = [
        'answer',
        'greeting',
        'help',
        'change_language',
        'skip',
        'back',
        'cancel',
        'save_and_continue',
        'create_new_cv',
        'view_history',
        'edit_section',
        'confirm',
        'reject',
        'clarification',
        'unrelated_request',
        'unknown',
    ];

    private const HISTORY_LIMIT = 20;

    private const MESSAGE_CONTENT_LIMIT = 2000;

    private const ANSWER_LIMIT = 5000;

    private const SECTION_LIMIT = 100;

    private const CLARIFICATION_REASON_LIMIT = 500;

    private const SUPPORTED_LANGUAGES = [
        'en',
        'sw',
        'mixed',
        'unknown',
    ];

    public function __construct(
        private readonly AIProvider $provider,
    ) {
    }

    /**
     * Interpret a message using validated conversation context.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function classify(
        string $message,
        array $context = [],
    ): array {
        $providerContext = [
            'current_state' => $context['current_state'] ?? null,
            'previous_state' => $context['previous_state'] ?? null,
            'language' => $context['language'] ?? null,
            'pending_question' => $context['pending_question'] ?? null,
            'conversation_history' => $this->validatedHistory(
                $context['conversation_history'] ?? []
            ),
            'allowed_intents' => self::ALLOWED_INTENTS,
        ];

        $result = $this->provider->interpret(
            $message,
            $providerContext,
        );

        $intent = $result['intent'] ?? null;
        $confidence = $result['confidence'] ?? null;
        $data = $result['data'] ?? null;

        if (
            ! is_string($intent)
            || ! in_array($intent, self::ALLOWED_INTENTS, true)
            || ! is_numeric($confidence)
            || is_bool($confidence)
            || ! is_array($data)
            || (array_is_list($data) && $data !== [])
        ) {
            return $this->clarificationResult();
        }

        $confidence = (float) $confidence;

        if (
            ! is_finite($confidence)
            || $confidence < 0
            || $confidence > 1
        ) {
            return $this->clarificationResult();
        }

        $validatedData = $this->validatedData($data);

        if ($validatedData === null) {
            return $this->clarificationResult();
        }

        $requiresClarification =
            $intent === 'unknown'
            || $intent === 'clarification'
            || $confidence < self::MIN_CONFIDENCE
            || $validatedData['needs_clarification'];

        return [
            'intent' => $intent,
            'confidence' => $confidence,
            'data' => $validatedData,
            'requires_clarification' => $requiresClarification,
        ];
    }

    /**
     * Validate Gemini's structured interpretation data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function validatedData(array $data): ?array
    {
        $requiredKeys = [
            'conversation_language',
            'target_section',
            'extracted_answer',
            'needs_clarification',
            'clarification_reason',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $data)) {
                return null;
            }
        }

        $language = $data['conversation_language'];
        $targetSection = $data['target_section'];
        $extractedAnswer = $data['extracted_answer'];
        $needsClarification = $data['needs_clarification'];
        $clarificationReason = $data['clarification_reason'];

        if (
            ! is_string($language)
            || ! in_array(
                $language,
                self::SUPPORTED_LANGUAGES,
                true
            )
        ) {
            return null;
        }

        if (
            $targetSection !== null
            && (
                ! is_string($targetSection)
                || mb_strlen($targetSection) > self::SECTION_LIMIT
            )
        ) {
            return null;
        }

        if (
            $extractedAnswer !== null
            && (
                ! is_string($extractedAnswer)
                || mb_strlen($extractedAnswer) > self::ANSWER_LIMIT
            )
        ) {
            return null;
        }

        if (! is_bool($needsClarification)) {
            return null;
        }

        if (
            $clarificationReason !== null
            && (
                ! is_string($clarificationReason)
                || mb_strlen($clarificationReason)
                    > self::CLARIFICATION_REASON_LIMIT
            )
        ) {
            return null;
        }

        return [
            'conversation_language' => $language,
            'target_section' => $targetSection,
            'extracted_answer' => $extractedAnswer,
            'needs_clarification' => $needsClarification,
            'clarification_reason' => $clarificationReason,
        ];
    }

    /**
     * Keep only valid, bounded conversation history entries.
     *
     * @return list<array{
     *     role: string,
     *     content: string,
     *     timestamp?: string
     * }>
     */
    private function validatedHistory(mixed $history): array
    {
        if (! is_array($history)) {
            return [];
        }

        $validated = [];

        foreach (array_slice($history, -self::HISTORY_LIMIT) as $entry) {
            if (
                ! is_array($entry)
                || ! in_array(
                    $entry['role'] ?? null,
                    ['user', 'assistant'],
                    true
                )
                || ! is_string($entry['content'] ?? null)
            ) {
                continue;
            }

            $item = [
                'role' => $entry['role'],
                'content' => mb_substr(
                    $entry['content'],
                    0,
                    self::MESSAGE_CONTENT_LIMIT
                ),
            ];

            if (is_string($entry['timestamp'] ?? null)) {
                $item['timestamp'] = $entry['timestamp'];
            }

            $validated[] = $item;
        }

        return $validated;
    }

    /**
     * @return array{
     *     intent: string,
     *     confidence: float,
     *     data: array<string, mixed>,
     *     requires_clarification: bool
     * }
     */
    private function clarificationResult(): array
    {
        return [
            'intent' => 'clarification',
            'confidence' => 0.0,
            'data' => [],
            'requires_clarification' => true,
        ];
    }
}
