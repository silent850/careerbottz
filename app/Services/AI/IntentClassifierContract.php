<?php

namespace App\Services\AI;

interface IntentClassifierContract
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function classify(string $message, array $context = []): array;
}