<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AIProvider;
use App\Services\AI\IntentClassifier;
use Tests\TestCase;

class IntentClassifierTest extends TestCase
{
    public function test_it_accepts_a_valid_high_confidence_intent(): void
    {
        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'help',
                'confidence' => 0.95,
                'data' => $this->validData(),
            ]),
        );

        $result = $classifier->classify(
            'Please help me',
            [
                'current_state' => 'welcome',
                'language' => 'en',
            ],
        );

        $this->assertSame('help', $result['intent']);
        $this->assertSame(0.95, $result['confidence']);
        $this->assertFalse($result['requires_clarification']);
        $this->assertSame('en', $result['data']['conversation_language']);
    }

    public function test_it_requests_clarification_for_low_confidence(): void
    {
        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'answer',
                'confidence' => 0.40,
                'data' => $this->validData(),
            ]),
        );

        $result = $classifier->classify('Something unclear');

        $this->assertTrue($result['requires_clarification']);
    }

    public function test_it_rejects_an_unrecognized_intent(): void
    {
        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'delete_everything',
                'confidence' => 0.99,
                'data' => $this->validData(),
            ]),
        );

        $result = $classifier->classify('Delete everything');

        $this->assertSame('clarification', $result['intent']);
        $this->assertTrue($result['requires_clarification']);
    }

    public function test_it_rejects_malformed_provider_results(): void
    {
        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'help',
                'confidence' => 'very sure',
                'data' => $this->validData(),
            ]),
        );

        $result = $classifier->classify('Help');

        $this->assertSame('clarification', $result['intent']);
        $this->assertTrue($result['requires_clarification']);
    }

    public function test_it_rejects_confidence_outside_zero_to_one(): void
    {
        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'help',
                'confidence' => 1.5,
                'data' => $this->validData(),
            ]),
        );

        $result = $classifier->classify('Help');

        $this->assertSame(0.0, $result['confidence']);
        $this->assertTrue($result['requires_clarification']);
    }

    public function test_it_marks_unknown_intent_for_clarification(): void
    {
        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'unknown',
                'confidence' => 0.99,
                'data' => $this->validData(),
            ]),
        );

        $result = $classifier->classify('Unclear request');

        $this->assertSame('unknown', $result['intent']);
        $this->assertTrue($result['requires_clarification']);
    }

    public function test_it_accepts_greeting_intent(): void
    {
        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'greeting',
                'confidence' => 0.98,
                'data' => $this->validData('sw'),
            ]),
        );

        $result = $classifier->classify('Mambo');

        $this->assertSame('greeting', $result['intent']);
        $this->assertFalse($result['requires_clarification']);
        $this->assertSame('sw', $result['data']['conversation_language']);
    }

    public function test_it_accepts_unrelated_request_intent(): void
    {
        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'unrelated_request',
                'confidence' => 0.95,
                'data' => $this->validData(),
            ]),
        );

        $result = $classifier->classify('What is the weather?');

        $this->assertSame('unrelated_request', $result['intent']);
        $this->assertFalse($result['requires_clarification']);
    }

    public function test_it_respects_gemini_clarification_flag(): void
    {
        $data = $this->validData();
        $data['needs_clarification'] = true;
        $data['clarification_reason'] = 'The answer is ambiguous.';

        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'answer',
                'confidence' => 0.98,
                'data' => $data,
            ]),
        );

        $result = $classifier->classify('I worked there before');

        $this->assertSame('answer', $result['intent']);
        $this->assertTrue($result['requires_clarification']);
    }

    public function test_it_rejects_missing_structured_data_fields(): void
    {
        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'help',
                'confidence' => 0.95,
                'data' => [],
            ]),
        );

        $result = $classifier->classify('Help');

        $this->assertSame('clarification', $result['intent']);
        $this->assertTrue($result['requires_clarification']);
    }

    public function test_it_rejects_unsupported_conversation_language(): void
    {
        $data = $this->validData();
        $data['conversation_language'] = 'fr';

        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'help',
                'confidence' => 0.95,
                'data' => $data,
            ]),
        );

        $result = $classifier->classify('Help');

        $this->assertSame('clarification', $result['intent']);
        $this->assertTrue($result['requires_clarification']);
    }

    public function test_it_rejects_non_boolean_clarification_flag(): void
    {
        $data = $this->validData();
        $data['needs_clarification'] = 'false';

        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'help',
                'confidence' => 0.95,
                'data' => $data,
            ]),
        );

        $result = $classifier->classify('Help');

        $this->assertSame('clarification', $result['intent']);
        $this->assertTrue($result['requires_clarification']);
    }

    public function test_it_preserves_extracted_answer(): void
    {
        $data = $this->validData();
        $data['target_section'] = 'experience';
        $data['extracted_answer'] = 'I worked at Bahi District Council.';

        $classifier = new IntentClassifier(
            $this->provider([
                'intent' => 'answer',
                'confidence' => 0.96,
                'data' => $data,
            ]),
        );

        $result = $classifier->classify(
            'I worked at Bahi District Council.'
        );

        $this->assertSame(
            'I worked at Bahi District Council.',
            $result['data']['extracted_answer']
        );

        $this->assertSame(
            'experience',
            $result['data']['target_section']
        );
    }

    private function validData(string $language = 'en'): array
    {
        return [
            'conversation_language' => $language,
            'target_section' => null,
            'extracted_answer' => null,
            'needs_clarification' => false,
            'clarification_reason' => null,
        ];
    }

    private function provider(array $result): AIProvider
    {
        return new class($result) implements AIProvider {
            public function __construct(
                private readonly array $result,
            ) {
            }

            public function interpret(
                string $message,
                array $context = [],
            ): array {
                return $this->result;
            }

            public function generateConversationalResponse(
                string $message,
                array $context = [],
            ): string {
                return '';
            }

            public function writeProfessionalContent(
                string $task,
                array $facts,
            ): string {
                return '';
            }
        };
    }
}