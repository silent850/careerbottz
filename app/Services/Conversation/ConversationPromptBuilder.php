<?php

namespace App\Services\Conversation;

final class ConversationPromptBuilder
{
    /**
     * Return the prompt for a conversation state in the selected language.
     *
     * These prompts guide the conversation only. CV collection and
     * persistence will be implemented in Phase 5.
     */
    public function forState(
        ConversationState $state,
        string $language,
    ): string {
        $kiswahili = $language === ConversationLanguage::KISWAHILI;

        return match ($state) {
            ConversationState::WELCOME => $kiswahili
                ? 'Karibu CareerBotTz! Andika "help" kuona chaguo au "new CV" kuanza CV.'
                : 'Welcome to CareerBotTz! Send "help" for options or "new CV" to start a CV.',

            ConversationState::LANGUAGE_SELECTION => $kiswahili
                ? 'Tafadhali chagua lugha: English au Kiswahili.'
                : 'Please choose your language: English or Kiswahili.',

            ConversationState::CV_SELECTION => $kiswahili
                ? 'Ungependa kuanza CV mpya au kuendelea na CV iliyopo?'
                : 'Would you like to start a new CV or continue an existing one?',

            ConversationState::CV_NAMING => $kiswahili
                ? 'Tafadhali weka jina la CV yako.'
                : 'Please enter a name for your CV.',

            ConversationState::CV_PURPOSE => $kiswahili
                ? 'Ungependa kuunda CV ya jumla au CV inayolenga nafasi maalumu ya kazi? Jibu "general" kwa CV ya jumla au "targeted" kwa CV inayolenga kazi maalumu.'
                : 'Would you like to create a general CV or a job-targeted CV? Reply "general" for a general CV or "targeted" for a CV tailored to a specific job advertisement.',

            ConversationState::PERSONAL_DETAILS => $kiswahili
                ? 'Tafadhali shiriki taarifa zako za msingi kwa ajili ya CV yako.'
                : 'Please share your basic personal details for your CV.',

            ConversationState::EDUCATION => $kiswahili
                ? 'Tafadhali shiriki taarifa zako za elimu.'
                : 'Please share your education details.',

            ConversationState::EXPERIENCE => $kiswahili
                ? 'Tafadhali shiriki uzoefu wako wa kazi, ikiwa unao.'
                : 'Please share your work experience, if any.',

            ConversationState::SKILLS => $kiswahili
                ? 'Tafadhali taja ujuzi na uwezo wako.'
                : 'Please list your skills and competencies.',

            ConversationState::PROJECTS => $kiswahili
                ? 'Tafadhali shiriki miradi yako, ikiwa ipo.'
                : 'Please share any projects you would like to include.',

            ConversationState::CERTIFICATIONS => $kiswahili
                ? 'Tafadhali shiriki vyeti au leseni zako, ikiwa unazo.'
                : 'Please share any certifications or licences you have.',

            ConversationState::LANGUAGES => $kiswahili
                ? 'Tafadhali taja lugha unazozungumza na kiwango chako.'
                : 'Please list the languages you speak and your proficiency levels.',

            ConversationState::ADDITIONAL_SECTIONS => $kiswahili
                ? 'Je, ungependa kuongeza taarifa nyingine kwenye CV yako?'
                : 'Would you like to add any other sections to your CV?',

            ConversationState::CONTENT_REVIEW => $kiswahili
                ? 'Tafadhali kagua taarifa za CV yako.'
                : 'Please review your CV information.',

            ConversationState::CV_PREVIEW => $kiswahili
                ? 'Huu ndio muhtasari wa CV yako kwa ajili ya mapitio.'
                : 'Here is your CV preview for review.',

            ConversationState::AWAITING_CONFIRMATION => $kiswahili
                ? 'Je, unathibitisha taarifa hizi?'
                : 'Do you confirm these details?',

            ConversationState::GENERATING_DOCUMENT => $kiswahili
                ? 'Tafadhali subiri wakati hati yako inaandaliwa.'
                : 'Please wait while your document is being prepared.',

            ConversationState::COMPLETED => $kiswahili
                ? 'Hongera! Hatua hii imekamilika.'
                : 'Congratulations! This step is complete.',
        };
    }
}
