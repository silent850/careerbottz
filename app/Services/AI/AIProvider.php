<?php

namespace App\Services\AI;

interface AIProvider
{
    /**
     * Interpret a user message and return structured data.
     *
     * The caller must validate the returned data before use.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function interpret(
        string $message,
        array $context = [],
    ): array;

    /**
     * Generate a natural conversational response.
     *
     * Responses must follow the selected conversation language
     * and use the supplied conversation context.
     *
     * @param array<string, mixed> $context
     */
    public function generateConversationalResponse(
        string $message,
        array $context = [],
    ): string;

    /**
     * Generate professional English content from supplied facts.
     *
     * @param array<string, mixed> $facts
     */
    public function writeProfessionalContent(
        string $task,
        array $facts,
    ): string;
}
