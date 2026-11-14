<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\GeminiAIProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiAIProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key' => 'test-api-key',
            'services.gemini.base_url' =>
                'https://generativelanguage.googleapis.com/v1beta',
            'services.gemini.model' => 'gemini-3.5-flash-lite',
            'services.gemini.timeout' => 30,
            'services.gemini.connect_timeout' => 5,
        ]);
    }

    public function test_it_interprets_a_message_as_a_json_object(): void
    {
        Http::fake([
            '*' => Http::response([
                'output_text' => json_encode([
                    'intent' => 'help',
                    'confidence' => 0.95,
                ]),
            ], 200),
        ]);

        $result = (new GeminiAIProvider())->interpret(
            'Help me with my CV',
            ['state' => 'welcome'],
        );

        $this->assertSame('help', $result['intent']);
        $this->assertSame(0.95, $result['confidence']);

        Http::assertSent(function ($request): bool {
            return str_ends_with(
                $request->url(),
                '/v1beta/interactions',
            )
                && $request->hasHeader(
                    'x-goog-api-key',
                    'test-api-key',
                )
                && $request['model'] === 'gemini-3.5-flash-lite'
                && $request['store'] === false
                && $request['response_format']['mime_type']
                    === 'application/json';
        });
    }

    public function test_it_extracts_text_from_interaction_steps(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'completed',
                'steps' => [
                    [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => json_encode([
                                    'intent' => 'help',
                                    'confidence' => 0.9,
                                ]),
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new GeminiAIProvider())->interpret('Help me');

        $this->assertSame('help', $result['intent']);
        $this->assertSame(0.9, $result['confidence']);
    }

    public function test_it_generates_professional_english_content(): void
    {
        Http::fake([
            '*' => Http::response([
                'output_text' => 'Experienced Human Resources Assistant.',
            ], 200),
        ]);

        $result = (new GeminiAIProvider())->writeProfessionalContent(
            'Write a professional summary',
            ['experience' => 'Human Resources Assistant'],
        );

        $this->assertSame(
            'Experienced Human Resources Assistant.',
            $result,
        );

        Http::assertSent(function ($request): bool {
            return $request['response_format']['mime_type']
                === 'text/plain'
                && $request['store'] === false;
        });
    }

    public function test_it_extracts_professional_content_from_interaction_steps(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'completed',
                'steps' => [
                    [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'A professional CV summary.',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new GeminiAIProvider())->writeProfessionalContent(
            'Write a professional summary',
            ['experience' => 'HR Assistant'],
        );

        $this->assertSame('A professional CV summary.', $result);
    }

    public function test_it_rejects_invalid_json_from_gemini(): void
    {
        Http::fake([
            '*' => Http::response([
                'output_text' => 'not valid JSON',
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini returned invalid JSON.');

        (new GeminiAIProvider())->interpret('Hello');
    }

    public function test_it_rejects_a_non_object_json_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'output_text' => '["unexpected", "list"]',
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Gemini returned an invalid JSON object.',
        );

        (new GeminiAIProvider())->interpret('Hello');
    }

    public function test_it_rejects_empty_output_from_interaction_steps(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'completed',
                'steps' => [
                    [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => " \n ",
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Gemini returned no usable text output.',
        );

        (new GeminiAIProvider())->interpret('Hello');
    }

    public function test_it_rejects_an_unsuccessful_api_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'Private provider details'],
            ], 503),
        ]);

        try {
            (new GeminiAIProvider())->interpret('Hello');
            $this->fail('Expected the Gemini request to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Gemini request failed with HTTP status 503: Private provider details',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_fails_safely_when_api_key_is_missing(): void
    {
        config(['services.gemini.api_key' => null]);

        Http::fake();

        try {
            (new GeminiAIProvider())->interpret('Hello');
            $this->fail('Expected missing credentials to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Gemini API credentials are not configured.',
                $exception->getMessage(),
            );
        }

        Http::assertNothingSent();
    }

    public function test_it_handles_connection_failures(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Simulated connection failure');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Gemini could not be reached or the request timed out.',
        );

        (new GeminiAIProvider())->interpret('Hello');
    }


    
    public function test_it_generates_a_contextual_conversational_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'output_text' => 'Karibu! Naweza kukusaidia kuandaa CV yako.',
            ], 200),
        ]);

        $context = [
            'language' => 'sw',
            'current_state' => 'personal_details',
            'previous_state' => 'cv_naming',
            'pending_question' => 'Please share your personal details.',
            'conversation_history' => [
                [
                    'role' => 'assistant',
                    'content' => 'Tafadhali weka jina la CV yako.',
                ],
                [
                    'role' => 'user',
                    'content' => 'CV yangu inaitwa Professional CV.',
                ],
            ],
        ];

        $result = (new GeminiAIProvider())
            ->generateConversationalResponse(
                'Naweza kuendelea?',
                $context,
            );

        $this->assertSame(
            'Karibu! Naweza kukusaidia kuandaa CV yako.',
            $result,
        );

        Http::assertSent(function ($request): bool {
            $input = $request['input'] ?? '';

            return is_string($input)
                && str_contains($input, 'CareerBotTz')
                && str_contains($input, 'Kiswahili')
                && str_contains($input, 'personal_details')
                && str_contains($input, 'Naweza kuendelea?')
                && $request['response_format']['type'] === 'text'
                && $request['response_format']['mime_type'] === 'text/plain'
                && $request['store'] === false;
        });
    }

    public function test_it_generates_conversational_text_from_interaction_steps(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'completed',
                'steps' => [
                    [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Hello! How can I help with your CV?',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new GeminiAIProvider())
            ->generateConversationalResponse(
                'Hello',
                ['language' => 'en'],
            );

        $this->assertSame(
            'Hello! How can I help with your CV?',
            $result,
        );
    }

}