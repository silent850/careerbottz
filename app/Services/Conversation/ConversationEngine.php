<?php

namespace App\Services\Conversation;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\IntentClassifierContract;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Services\Conversation\ResponseGeneratorContract;

final class ConversationEngine
{
    private const INACTIVITY_MINUTES = 60;

    private readonly ConversationContextBuilder $contextBuilder;

    public function __construct(
        private readonly IntentClassifierContract $intentClassifier,
        private readonly ConversationLanguage $conversationLanguage,
        private readonly ConversationPromptBuilder $promptBuilder,
        private readonly ResponseGeneratorContract $responseGenerator,
        ?ConversationContextBuilder $contextBuilder = null,
    ) {
        $this->contextBuilder = $contextBuilder
            ?? new ConversationContextBuilder();
    }

    public function prepare(
        User $user,
        string $message,
        ?Message $inboundMessage = null,
    ): PreparedConversation {
        $message = trim($message);

        if ($message === '') {
            return new PreparedConversation(
                response: new ConversationResponse(
                    reply: 'Please send a message. / Tafadhali tuma ujumbe.',
                    nextState: ConversationState::WELCOME,
                    requiresClarification: true,
                ),
                userId: (int) $user->id,
                conversationId: null,
                expectedState: null,
                expectedPreviousState: null,
                expectedStatus: null,
                expectedLatestInboundMessageId: null,
            );
        }

        $currentUser = User::query()->findOrFail($user->getKey());

        $this->validateInboundMessage(
            $inboundMessage,
            $currentUser,
        );

        $conversation = Conversation::query()
            ->where('user_id', $currentUser->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (
            $conversation !== null
            && $conversation->updated_at !== null
            && $conversation->updated_at->lte(
                now()->subMinutes(self::INACTIVITY_MINUTES)
            )
        ) {
            $conversation = null;
        }

        if ($conversation === null) {
            $initialState = $currentUser->preferred_language
                ? ConversationState::WELCOME
                : ConversationState::LANGUAGE_SELECTION;

            $language = $this->conversationLanguage->resolve(
                $currentUser->preferred_language
            );
$reply = $initialState === ConversationState::LANGUAGE_SELECTION
    ? 'Welcome to CareerBotTz! Choose your language: English or Kiswahili. / Karibu CareerBotTz! Chagua lugha: English au Kiswahili.'
    : (
        $language === ConversationLanguage::KISWAHILI
            ? 'Karibu tena CareerBotTz! 👋

Unaweza kutumia amri zifuatazo:

• help – Pata msaada na orodha ya amri.
• change language – Badilisha lugha.
• new CV – Anza kuunda CV mpya.
• cancel – Sitisha mazungumzo.
• save and continue – Hifadhi maendeleo na uendelee baadaye.
• back – Rudi hatua iliyopita.
• skip – Ruka swali la sasa.
• view history – Angalia historia ya CV zako.
• edit section – Hariri sehemu ya CV.
• confirm – Thibitisha.
• reject – Kataa.

Unaweza pia kuniandikia kwa lugha ya kawaida kueleza unachohitaji. Kumbuka baadhi ya vipengele bado vinaendelea kutengenezwa.'
            : 'Welcome back to CareerBotTz! 👋

You can use these commands:

• help – Get help and the command list.
• change language – Change conversation language.
• new CV – Start creating a new CV.
• cancel – Cancel the conversation.
• save and continue – Save progress and continue later.
• back – Return to the previous step.
• skip – Skip the current question.
• view history – View your CV history.
• edit section – Edit a CV section.
• confirm – Confirm.
• reject – Reject.

You can also describe what you need in natural language. Please note that some features are still under development.'
    );

            return new PreparedConversation(
                response: new ConversationResponse(
                    reply: $reply,
                    nextState: $initialState,
                ),
                userId: (int) $currentUser->id,
                conversationId: null,
                expectedState: null,
                expectedPreviousState: null,
                expectedStatus: null,
                expectedLatestInboundMessageId: null,
                createConversation: true,
            );
        }

        $this->validateInboundMessage(
            $inboundMessage,
            $currentUser,
            $conversation,
        );

        $latestInboundMessageId = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->max('id');

        $snapshot = [
            'conversationId' => (int) $conversation->id,
            'expectedState' => $conversation->state->value,
            'expectedPreviousState' => $conversation->previous_state,
            'expectedStatus' => $conversation->status,
            'expectedLatestInboundMessageId' => $latestInboundMessageId === null
                ? null
                : (int) $latestInboundMessageId,
        ];

        if ($conversation->state === ConversationState::LANGUAGE_SELECTION) {
            $selectedLanguage = $this->conversationLanguage->resolve($message);

            if ($selectedLanguage === null) {
                return $this->prepared(
                    response: new ConversationResponse(
                        reply: 'Please choose English or Kiswahili. / Tafadhali chagua English au Kiswahili.',
                        nextState: ConversationState::LANGUAGE_SELECTION,
                        requiresClarification: true,
                    ),
                    user: $currentUser,
                    snapshot: $snapshot,
                );
            }

            $restoredState = $conversation->previous_state
                ? ConversationState::from($conversation->previous_state)
                : ConversationState::CV_SELECTION;

            return $this->prepared(
                response: new ConversationResponse(
                    reply: $this->promptBuilder->forState(
                        $restoredState,
                        $selectedLanguage,
                    ),
                    nextState: $restoredState,
                ),
                user: $currentUser,
                snapshot: $snapshot,
                preferredLanguage: $selectedLanguage,
                updatePreferredLanguage: true,
                previousState: null,
                updatePreviousState: true,
            );
        }

        $language = $this->conversationLanguage->resolve(
            $currentUser->preferred_language
        );

        $pendingQuestion = $this->promptBuilder->forState(
            $conversation->state,
            $language,
        );

        $context = $this->contextBuilder->build(
            $conversation,
            $language,
            $pendingQuestion,
            $inboundMessage?->id,
        );

        $classification = $this->intentClassifier->classify(
            $message,
            $context,
        );

        if ($classification['requires_clarification']) {
            $reply = $this->responseGenerator->generate(
                $message,
                [
                    ...$context,
                    'intent' => $classification['intent'] ?? 'clarification',
                    'requires_clarification' => true,
                ],
            );

            return $this->prepared(
                response: new ConversationResponse(
                    reply: $reply,
                    nextState: $conversation->state,
                    requiresClarification: true,
                ),
                user: $currentUser,
                snapshot: $snapshot,
            );
        }

        $nextState = $conversation->state;
        $previousState = null;
        $updatePreviousState = false;
        $conversationStatus = null;

        $extractedAnswer = trim(
            (string) ($classification['data']['extracted_answer'] ?? '')
        );

        switch ($classification['intent']) {
            case 'answer':
                if ($extractedAnswer === '') {
                    $reply = $language === ConversationLanguage::KISWAHILI
                        ? 'Samahani, sijapata jibu lako vizuri. Tafadhali fafanua tena.'
                        : 'Sorry, I could not identify your answer clearly. Please clarify it.';
                    break;
                }

                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Nimeelewa jibu lako. Tafadhali endelea; uhifadhi wa taarifa za CV utaunganishwa kwenye hatua ya ukusanyaji wa CV.'
                    : 'I understood your answer. Please continue; saving CV information will be connected in the CV collection stage.';
                break;

            case 'help':
                // Always return the complete command menu.
                // Do not depend on Gemini to generate the available commands.
                $reply = $this->helpMessage($language);
                break;

            case 'greeting':
            case 'clarification':
            case 'unrelated_request':
                $reply = $this->responseGenerator->generate(
                    $message,
                    [
                        ...$context,
                        'intent' => $classification['intent'],
                    ],
                );
                break;

            case 'change_language':
                $previousState = $conversation->state->value;
                $updatePreviousState = true;
                $nextState = ConversationState::LANGUAGE_SELECTION;
                $reply = 'Choose English or Kiswahili. / Chagua English au Kiswahili.';
                break;

            case 'cancel':
                $conversationStatus = 'cancelled';

                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Mazungumzo haya yamesitishwa. Tuma ujumbe mpya ukitaka kuendelea.'
                    : 'This conversation has been cancelled. Send a new message whenever you want to continue.';
                break;

            case 'save_and_continue':
                $conversationStatus = 'paused';

                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Maendeleo yako yamehifadhiwa. Tuma ujumbe mpya ukitaka kuendelea.'
                    : 'Your progress has been saved. Send a new message when you want to continue.';
                break;

            case 'back':
                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Kurudi hatua iliyopita kutawezeshwa baada ya mtiririko wa sehemu za CV kuunganishwa.'
                    : 'Going back will be available when the CV section flow is connected.';
                break;

            case 'skip':
                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Kuruka sehemu kutawezeshwa baada ya sheria za sehemu za CV kuunganishwa.'
                    : 'Skipping will be available once the CV section rules are connected.';
                break;

            case 'create_new_cv':
                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Mtiririko wa kuunda CV utaunganishwa baada ya hifadhi ya CV na sehemu zake kukamilika.'
                    : 'The CV creation flow will be connected after CV storage and section handlers are implemented.';
                break;

            case 'view_history':
                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Historia ya CV itapatikana baada ya hifadhi ya historia ya CV kukamilika.'
                    : 'CV history will be available after CV history storage is implemented.';
                break;

            case 'edit_section':
                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Kubadilisha sehemu za CV kutawezeshwa baada ya mtiririko wa uhariri wa CV kuunganishwa.'
                    : 'Editing CV sections will be available once the CV editing workflow is connected.';
                break;

            case 'confirm':
            case 'reject':
                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Nimepokea jibu lako. Uthibitishaji utaunganishwa kwenye hatua ya mapitio ya CV.'
                    : 'I received your response. Confirmation handling will be connected during the CV review stage.';
                break;

            default:
                $reply = $language === ConversationLanguage::KISWAHILI
                    ? 'Tafadhali fafanua ujumbe wako au andika "help" kupata msaada.'
                    : 'Please clarify your message or send "help" for assistance.';
                break;
        }

        return $this->prepared(
            response: new ConversationResponse(
                reply: $reply,
                nextState: $nextState,
            ),
            user: $currentUser,
            snapshot: $snapshot,
            previousState: $previousState,
            updatePreviousState: $updatePreviousState,
            conversationStatus: $conversationStatus,
        );
    }

    public function commit(
        PreparedConversation $prepared,
        ?Message $inboundMessage = null,
    ): ConversationResponse {
        $user = User::query()
            ->whereKey($prepared->userId)
            ->lockForUpdate()
            ->firstOrFail();

        $this->validateInboundMessage(
            $inboundMessage,
            $user,
        );

        $conversation = Conversation::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($prepared->createConversation) {
            if ($conversation !== null) {
                $isExpired = $conversation->updated_at !== null
                    && $conversation->updated_at->lte(
                        now()->subMinutes(self::INACTIVITY_MINUTES)
                    );

                if (! $isExpired) {
                    throw new RuntimeException(
                        'Conversation changed while the response was being prepared. Retry processing.'
                    );
                }

                $conversation->status = 'expired';
                $conversation->save();
            }

            $conversation = Conversation::query()->create([
                'user_id' => $user->id,
                'state' => $prepared->response->nextState,
                'status' => 'active',
            ]);
        } elseif (
            $prepared->conversationId === null
            || $conversation === null
            || (int) $conversation->id !== $prepared->conversationId
        ) {
            throw new RuntimeException(
                'Conversation changed while the response was being prepared. Retry processing.'
            );
        }

        if (! $prepared->createConversation) {
            $latestInboundMessageId = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('direction', 'inbound')
                ->max('id');

            $latestInboundMessageId = $latestInboundMessageId === null
                ? null
                : (int) $latestInboundMessageId;

            if (
                $conversation->state->value !== $prepared->expectedState
                || $conversation->previous_state !== $prepared->expectedPreviousState
                || $conversation->status !== $prepared->expectedStatus
                || $latestInboundMessageId !== $prepared->expectedLatestInboundMessageId
            ) {
                throw new RuntimeException(
                    'Conversation changed while the response was being prepared. Retry processing.'
                );
            }
        }

        if ($prepared->updatePreferredLanguage) {
            $user->preferred_language = $prepared->preferredLanguage;
            $user->save();
        }

        if ($conversation !== null) {
            if ($prepared->updatePreviousState) {
                $conversation->previous_state = $prepared->previousState;
            }

            if ($prepared->conversationStatus !== null) {
                $conversation->status = $prepared->conversationStatus;
            } elseif ($conversation->status === 'active') {
                $conversation->state = $prepared->response->nextState;
            }

            $conversation->save();

            $this->attachInboundMessage(
                $inboundMessage,
                $user,
                $conversation,
            );
        }

        return $prepared->response;
    }

    private function prepared(
        ConversationResponse $response,
        User $user,
        array $snapshot,
        ?string $preferredLanguage = null,
        bool $updatePreferredLanguage = false,
        ?string $previousState = null,
        bool $updatePreviousState = false,
        ?string $conversationStatus = null,
    ): PreparedConversation {
        return new PreparedConversation(
            response: $response,
            userId: (int) $user->id,
            conversationId: $snapshot['conversationId'],
            expectedState: $snapshot['expectedState'],
            expectedPreviousState: $snapshot['expectedPreviousState'],
            expectedStatus: $snapshot['expectedStatus'],
            expectedLatestInboundMessageId: $snapshot['expectedLatestInboundMessageId'],
            preferredLanguage: $preferredLanguage,
            updatePreferredLanguage: $updatePreferredLanguage,
            previousState: $previousState,
            updatePreviousState: $updatePreviousState,
            conversationStatus: $conversationStatus,
        );
    }

    public function process(
        User $user,
        string $message,
        ?Message $inboundMessage = null,
    ): ConversationResponse {
        $prepared = $this->prepare(
            $user,
            $message,
            $inboundMessage,
        );

        if (trim($message) === '') {
            return $prepared->response;
        }

        return DB::transaction(function () use (
            $prepared,
            $inboundMessage,
        ): ConversationResponse {
            return $this->commit(
                $prepared,
                $inboundMessage,
            );
        });
    }

    private function validateInboundMessage(
        ?Message $message,
        User $user,
        ?Conversation $conversation = null,
    ): void {
        if ($message === null) {
            return;
        }

        if (
            $message->direction !== 'inbound'
            || (int) $message->user_id !== (int) $user->id
        ) {
            throw new RuntimeException(
                'Inbound message does not belong to this user.'
            );
        }

        if (
            $conversation !== null
            && $message->conversation_id !== null
            && (int) $message->conversation_id !== (int) $conversation->id
        ) {
            throw new RuntimeException(
                'Inbound message is already assigned to another conversation.'
            );
        }
    }

    private function attachInboundMessage(
        ?Message $message,
        User $user,
        Conversation $conversation,
    ): void {
        if ($message === null) {
            return;
        }

        $this->validateInboundMessage(
            $message,
            $user,
            $conversation,
        );

        if ($message->conversation_id === null) {
            $message->conversation_id = $conversation->id;
            $message->save();
        }
    }

    private function helpMessage(string $language): string
    {
        if ($language === ConversationLanguage::KISWAHILI) {
            return <<<'MESSAGE'
Karibu CareerBotTz! 👋

Hizi ndizo amri unazoweza kutumia:

1. help – Pata msaada na orodha ya amri.
2. change language – Badilisha lugha ya mazungumzo.
3. new CV – Anza kuunda CV mpya.
4. cancel – Sitisha mazungumzo ya sasa.
5. save and continue – Hifadhi maendeleo na uendelee baadaye.
6. back – Rudi kwenye hatua iliyopita.
7. skip – Ruka swali au hatua ya sasa.
8. view history – Angalia historia ya CV zako.
9. edit section – Hariri sehemu ya CV yako.
10. confirm – Thibitisha hatua au taarifa.
11. reject – Kataa hatua au taarifa.

Unaweza kutumia amri hizi kwa Kiingereza, Kiswahili au kueleza unachohitaji kwa lugha ya kawaida.

Kumbuka: Baadhi ya vipengele bado vinasubiri kuunganishwa kwenye mtiririko wa uundaji na uhariri wa CV.
MESSAGE;
        }

        return <<<'MESSAGE'
Welcome to CareerBotTz! 👋

Here are all the commands you can use:

1. help – Display this help menu.
2. change language – Change your conversation language.
3. new CV – Start creating a new CV.
4. cancel – Cancel the current conversation.
5. save and continue – Save your progress and continue later.
6. back – Return to the previous step.
7. skip – Skip the current question or step.
8. view history – View your previous CVs.
9. edit section – Edit a section of your CV.
10. confirm – Confirm an action or information.
11. reject – Reject an action or information.

You can use these commands in English, Kiswahili or describe what you need in natural language.

Note: Some features are still waiting to be connected to the CV creation and editing workflows.
MESSAGE;
    }
}