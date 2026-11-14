<?php

namespace Tests\Unit\Services\Conversation;

use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\IntentClassifierContract;
use App\Services\Conversation\ConversationEngine;
use App\Services\Conversation\ConversationLanguage;
use App\Services\Conversation\ConversationPromptBuilder;
use App\Services\Conversation\ConversationState;
use App\Services\Conversation\ResponseGeneratorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConversationEngineTest extends TestCase
{
    use RefreshDatabase;

    private IntentClassifierContract $classifier;

    private ResponseGeneratorContract $responseGenerator;

    private ConversationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = Mockery::mock(
            IntentClassifierContract::class
        );

      $this->responseGenerator = Mockery::mock(
    ResponseGeneratorContract::class
);

        $this->engine = new ConversationEngine(
            $this->classifier,
            new ConversationLanguage(),
            new ConversationPromptBuilder(),
            $this->responseGenerator,
        );
    }



public function test_first_message_after_expiration_creates_a_fresh_conversation(): void
{
    $user = User::factory()->create([
        'preferred_language' => 'en',
    ]);

    $expiredConversation = Conversation::query()->create([
        'user_id' => $user->id,
        'state' => ConversationState::EDUCATION,
        'status' => 'expired',
    ]);

    $response = $this->engine->process(
        $user,
        'Hello again',
    );

    $this->assertSame(
        ConversationState::WELCOME,
        $response->nextState,
    );

    $this->assertDatabaseHas('conversations', [
        'id' => $expiredConversation->id,
        'status' => 'expired',
        'state' => ConversationState::EDUCATION->value,
    ]);

    $this->assertDatabaseHas('conversations', [
        'user_id' => $user->id,
        'status' => 'active',
        'state' => ConversationState::WELCOME->value,
    ]);

    $this->assertSame(
        2,
        Conversation::query()
            ->where('user_id', $user->id)
            ->count(),
    );
}


    public function test_empty_message_requests_clarification_without_creating_conversation(): void
    {
        $user = User::factory()->create();

        $response = $this->engine->process($user, '   ');

        $this->assertTrue($response->requiresClarification);
        $this->assertSame(
            ConversationState::WELCOME,
            $response->nextState
        );

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_first_message_from_user_without_language_preference_requests_language_selection(): void
    {
        $user = User::factory()->create([
            'preferred_language' => null,
        ]);

        $response = $this->engine->process($user, 'Hello');

        $this->assertSame(
            ConversationState::LANGUAGE_SELECTION,
            $response->nextState
        );

        $this->assertStringContainsString(
            'English',
            $response->reply
        );

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'state' => ConversationState::LANGUAGE_SELECTION->value,
            'status' => 'active',
        ]);
    }

    public function test_first_message_from_user_with_language_preference_receives_welcome_message(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);

        $response = $this->engine->process($user, 'Hello');

        $this->assertSame(
            ConversationState::WELCOME,
            $response->nextState
        );

        $this->assertStringContainsString(
            'Welcome back',
            $response->reply
        );

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'state' => ConversationState::WELCOME->value,
            'status' => 'active',
        ]);
    }

    public function test_help_intent_returns_help_without_changing_conversation_state(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'state' => ConversationState::PERSONAL_DETAILS,
            'status' => 'active',
        ]);

        $this->classifier->shouldReceive('classify')
            ->once()
            ->with(
                'help',
                Mockery::on(
                    fn (array $context): bool =>
                        $context['current_state']
                            === ConversationState::PERSONAL_DETAILS->value
                        && $context['language'] === 'en'
                )
            )
            ->andReturn([
                'intent' => 'help',
                'confidence' => 0.95,
                'data' => [],
                'requires_clarification' => false,
            ]);

        $this->responseGenerator->shouldReceive('generate')
            ->once()
            ->with(
                'help',
                Mockery::on(
                    fn (array $context): bool =>
                        $context['intent'] === 'help'
                        && $context['language'] === 'en'
                        && $context['current_state'] === ConversationState::PERSONAL_DETAILS->value
                )
            )
            ->andReturn('I can help you prepare your CV. You can start a new CV or change your language.');

        $response = $this->engine->process($user, 'help');

        $this->assertSame(
            'I can help you prepare your CV. You can start a new CV or change your language.',
            $response->reply
        );

        $this->assertSame(
            ConversationState::PERSONAL_DETAILS,
            $response->nextState
        );

        $this->assertSame(
            ConversationState::PERSONAL_DETAILS,
            $conversation->fresh()->state
        );
    }

    public function test_greeting_intent_uses_natural_response_generator(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'state' => ConversationState::PERSONAL_DETAILS,
            'status' => 'active',
        ]);

        $this->classifier->shouldReceive('classify')
            ->once()
            ->andReturn([
                'intent' => 'greeting',
                'confidence' => 0.95,
                'data' => [],
                'requires_clarification' => false,
            ]);

        $this->responseGenerator->shouldReceive('generate')
            ->once()
            ->with(
                'hello',
                Mockery::on(
                    fn (array $context): bool =>
                        $context['intent'] === 'greeting'
                        && $context['language'] === 'en'
                        && $context['current_state'] === ConversationState::PERSONAL_DETAILS->value
                )
            )
            ->andReturn('Hello! How can I help you with your CV today?');

        $response = $this->engine->process($user, 'hello');

        $this->assertSame(
            'Hello! How can I help you with your CV today?',
            $response->reply
        );

        $this->assertSame(
            ConversationState::PERSONAL_DETAILS,
            $response->nextState
        );

        $this->assertSame(
            ConversationState::PERSONAL_DETAILS,
            $conversation->fresh()->state
        );
    }

    public function test_clarification_intent_uses_natural_response_generator(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'state' => ConversationState::EDUCATION,
            'status' => 'active',
        ]);

        $this->classifier->shouldReceive('classify')
            ->once()
            ->andReturn([
                'intent' => 'clarification',
                'confidence' => 0.95,
                'data' => [],
                'requires_clarification' => true,
            ]);

        $this->responseGenerator->shouldReceive('generate')
            ->once()
            ->with(
                'what do you mean?',
                Mockery::on(
                    fn (array $context): bool =>
                        $context['intent'] === 'clarification'
                        && $context['language'] === 'en'
                        && $context['current_state'] === ConversationState::EDUCATION->value
                )
            )
            ->andReturn('Could you tell me which part you would like me to explain?');

        $response = $this->engine->process(
            $user,
            'what do you mean?'
        );

        $this->assertSame(
            'Could you tell me which part you would like me to explain?',
            $response->reply
        );

        $this->assertTrue($response->requiresClarification);

        $this->assertSame(
            ConversationState::EDUCATION,
            $response->nextState
        );

        $this->assertSame(
            ConversationState::EDUCATION,
            $conversation->fresh()->state
        );
    }

    public function test_unrelated_request_uses_natural_response_generator(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'state' => ConversationState::PERSONAL_DETAILS,
            'status' => 'active',
        ]);

        $this->classifier->shouldReceive('classify')
            ->once()
            ->andReturn([
                'intent' => 'unrelated_request',
                'confidence' => 0.95,
                'data' => [],
                'requires_clarification' => false,
            ]);

        $this->responseGenerator->shouldReceive('generate')
            ->once()
            ->with(
                'tell me a football joke',
                Mockery::on(
                    fn (array $context): bool =>
                        $context['intent'] === 'unrelated_request'
                        && $context['language'] === 'en'
                        && $context['current_state'] === ConversationState::PERSONAL_DETAILS->value
                )
            )
            ->andReturn('I can help with your CV. What would you like to work on?');

        $response = $this->engine->process(
            $user,
            'tell me a football joke'
        );

        $this->assertSame(
            'I can help with your CV. What would you like to work on?',
            $response->reply
        );

        $this->assertSame(
            ConversationState::PERSONAL_DETAILS,
            $response->nextState
        );

        $this->assertSame(
            ConversationState::PERSONAL_DETAILS,
            $conversation->fresh()->state
        );
    }

    public function test_cancel_intent_marks_conversation_cancelled(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);

        Conversation::query()->create([
            'user_id' => $user->id,
            'state' => ConversationState::PERSONAL_DETAILS,
            'status' => 'active',
        ]);

        $this->classifier->shouldReceive('classify')
            ->once()
            ->andReturn([
                'intent' => 'cancel',
                'confidence' => 0.95,
                'data' => [],
                'requires_clarification' => false,
            ]);

        $response = $this->engine->process($user, 'cancel');

        $this->assertStringContainsString('cancelled', $response->reply);

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_save_and_continue_pauses_the_conversation(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);

        Conversation::query()->create([
            'user_id' => $user->id,
            'state' => ConversationState::EDUCATION,
            'status' => 'active',
        ]);

        $this->classifier->shouldReceive('classify')
            ->once()
            ->andReturn([
                'intent' => 'save_and_continue',
                'confidence' => 0.95,
                'data' => [],
                'requires_clarification' => false,
            ]);

        $response = $this->engine->process(
            $user,
            'save and continue'
        );

        $this->assertStringContainsString('saved', $response->reply);

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'status' => 'paused',
        ]);
    }

    public function test_low_confidence_classification_requests_clarification_and_preserves_state(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'state' => ConversationState::EDUCATION,
            'status' => 'active',
        ]);

        $this->classifier->shouldReceive('classify')
            ->once()
            ->andReturn([
                'intent' => 'answer',
                'confidence' => 0.30,
                'data' => [],
                'requires_clarification' => true,
            ]);

        $this->responseGenerator->shouldReceive('generate')
            ->once()
            ->with(
                'something unclear',
                Mockery::on(
                    fn (array $context): bool =>
                        $context['intent'] === 'answer'
                        && $context['requires_clarification'] === true
                        && $context['language'] === 'en'
                        && $context['current_state'] === ConversationState::EDUCATION->value
                )
            )
            ->andReturn('Sorry, could you explain that again?');

        $response = $this->engine->process(
            $user,
            'something unclear'
        );

        $this->assertSame(
            'Sorry, could you explain that again?',
            $response->reply
        );

        $this->assertTrue($response->requiresClarification);

        $this->assertSame(
            ConversationState::EDUCATION,
            $response->nextState
        );

        $this->assertSame(
            ConversationState::EDUCATION,
            $conversation->fresh()->state
        );
    }

    public function test_selecting_english_saves_preferred_language(): void
    {
        $user = User::factory()->create([
            'preferred_language' => null,
        ]);

        $this->engine->process($user, 'Hello');

        $response = $this->engine->process($user, 'English');

        $this->assertSame(
            ConversationState::CV_SELECTION,
            $response->nextState
        );

        $this->assertSame(
            'en',
            $user->fresh()->preferred_language
        );

        $this->assertSame(
            $this->enginePromptFor(
                ConversationState::CV_SELECTION,
                ConversationLanguage::ENGLISH,
            ),
            $response->reply
        );

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'state' => ConversationState::CV_SELECTION->value,
            'status' => 'active',
        ]);
    }

    public function test_selecting_kiswahili_saves_preferred_language(): void
    {
        $user = User::factory()->create([
            'preferred_language' => null,
        ]);

        $this->engine->process($user, 'Hello');

        $response = $this->engine->process($user, 'Kiswahili');

        $this->assertSame(
            ConversationState::CV_SELECTION,
            $response->nextState
        );

        $this->assertSame(
            'sw',
            $user->fresh()->preferred_language
        );

        $this->assertSame(
            $this->enginePromptFor(
                ConversationState::CV_SELECTION,
                ConversationLanguage::KISWAHILI,
            ),
            $response->reply
        );
    }

    public function test_language_selection_accepts_normalized_language_codes(): void
    {
        $user = User::factory()->create([
            'preferred_language' => null,
        ]);

        $this->engine->process($user, 'Hello');

        $this->engine->process($user, ' SWAHILI ');

        $this->assertSame(
            'sw',
            $user->fresh()->preferred_language
        );
    }

    public function test_unsupported_language_does_not_change_preference_or_leave_selection_state(): void
    {
        $user = User::factory()->create([
            'preferred_language' => null,
        ]);

        $this->engine->process($user, 'Hello');

        $response = $this->engine->process($user, 'French');

        $this->assertTrue($response->requiresClarification);

        $this->assertSame(
            ConversationState::LANGUAGE_SELECTION,
            $response->nextState
        );

        $this->assertNull($user->fresh()->preferred_language);

        $this->assertDatabaseHas('conversations', [
            'user_id' => $user->id,
            'state' => ConversationState::LANGUAGE_SELECTION->value,
            'status' => 'active',
        ]);
    }

    public function test_changing_language_saves_the_new_preference_and_restores_previous_state(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);

        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'state' => ConversationState::PERSONAL_DETAILS,
            'status' => 'active',
        ]);

        $this->classifier->shouldReceive('classify')
            ->once()
            ->andReturn([
                'intent' => 'change_language',
                'confidence' => 0.95,
                'data' => [],
                'requires_clarification' => false,
            ]);

        $this->engine->process($user, 'change language');

        $this->assertSame(
            ConversationState::LANGUAGE_SELECTION,
            $conversation->fresh()->state
        );

    $this->assertSame(
            ConversationState::PERSONAL_DETAILS->value,
            $conversation->fresh()->previous_state
        );

        $response = $this->engine->process($user, 'Kiswahili');

        $this->assertSame(
            'sw',
            $user->fresh()->preferred_language
        );

        $this->assertSame(
            ConversationState::PERSONAL_DETAILS,
            $response->nextState
        );

        $this->assertSame(
            $this->enginePromptFor(
                ConversationState::PERSONAL_DETAILS,
                ConversationLanguage::KISWAHILI,
            ),
            $response->reply
        );

        $this->assertSame(
            ConversationState::PERSONAL_DETAILS,
            $conversation->fresh()->state
        );

        $this->assertNull(
            $conversation->fresh()->previous_state
        );
    }

    private function enginePromptFor(
        ConversationState $state,
        string $language,
    ): string {
        return (new ConversationPromptBuilder())->forState(
            $state,
            $language,
        );
    }
}