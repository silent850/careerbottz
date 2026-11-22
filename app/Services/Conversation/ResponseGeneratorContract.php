<?php

namespace App\Services\Conversation;

interface ResponseGeneratorContract
{
    /**
     * @param array<string, mixed> $context
     */
    public function generate(
        string $message,
        array $context = [],
    ): string;
}