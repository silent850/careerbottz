
import { sendInboundMessage } from './laravel-client.js';
import { resolvePhoneNumberForJid } from './jid-mapping.js';

function extractText(message) {
    const content = message?.ephemeralMessage?.message
        ?? message?.viewOnceMessage?.message
        ?? message?.viewOnceMessageV2?.message
        ?? message?.documentWithCaptionMessage?.message
        ?? message;

    return (
        content?.conversation ??
        content?.extendedTextMessage?.text ??
        content?.imageMessage?.caption ??
        content?.videoMessage?.caption ??
        content?.documentMessage?.caption ??
        content?.buttonsResponseMessage?.selectedDisplayText ??
        content?.listResponseMessage?.title ??
        content?.templateButtonReplyMessage?.selectedDisplayText ??
        content?.interactiveResponseMessage?.body?.text ??
        null
    );
}

function normalizePhoneJid(jid) {
    const phone = resolvePhoneNumberForJid(jid);

    return phone ? `+${phone}` : null;
}

export async function handleWhatsAppMessage(
    message,
    logger = console,
    send = sendInboundMessage,
    resolveLidPhoneNumber = async () => null,
) {
    const ignore = (reason, details = {}) => {
        logger.info?.(
            { reason, ...details },
            'WhatsApp message ignored.',
        );

        return { ignored: true, reason };
    };

    if (!message?.key) {
        return ignore('missing-message-key');
    }

    // This gateway handles incoming messages from other people.
    // Do not process messages sent by the linked WhatsApp account.
    if (message.key.fromMe === true) {
        return ignore('message-from-linked-account');
    }

    const remoteJid = message.key.remoteJid;
    const alternateJid = message.key.remoteJidAlt;

    if (typeof remoteJid !== 'string' || !remoteJid.trim()) {
        return ignore('missing-chat-id');
    }

    // Group messages are intentionally disabled.
    if (remoteJid.endsWith('@g.us')) {
        return ignore('group-chat-disabled');
    }

    // Ignore broadcast/status destinations rather than treating them
    // as individual contacts.
    if (
        remoteJid === 'status@broadcast' ||
        remoteJid.endsWith('@broadcast')
    ) {
        return ignore('unsupported-chat-type');
    }

    let senderPhone =
        normalizePhoneJid(remoteJid) ??
        normalizePhoneJid(alternateJid);

    // LID addresses do not contain a reliably recoverable phone number.
    // Resolve them through the mapping supplied by the Baileys socket.
    if (!senderPhone && remoteJid.endsWith('@lid')) {
        try {
            const phoneJid = await resolveLidPhoneNumber(remoteJid);

            senderPhone = normalizePhoneJid(phoneJid);
        } catch (error) {
            logger.warn?.(
                {
                    errorName: error?.name ?? 'Error',
                    remoteJidType: 'lid',
                },
                'Baileys LID mapping lookup failed.',
            );
        }
    }

    // Some events provide the alternate JID as a LID. Resolve that too.
    if (!senderPhone && typeof alternateJid === 'string') {
        const alternateIsLid = alternateJid.endsWith('@lid');

        if (alternateIsLid) {
            try {
                const phoneJid = await resolveLidPhoneNumber(alternateJid);

                senderPhone = normalizePhoneJid(phoneJid);
            } catch (error) {
                logger.warn?.(
                    {
                        errorName: error?.name ?? 'Error',
                        alternateJidType: 'lid',
                    },
                    'Alternate Baileys LID mapping lookup failed.',
                );
            }
        }
    }

    if (!senderPhone) {
        const reason = remoteJid.endsWith('@lid')
            ? 'unmapped-lid'
            : 'unsupported-chat-type';

        return ignore(reason, {
            remoteJidType: remoteJid.endsWith('@lid')
                ? 'lid'
                : remoteJid.endsWith('@s.whatsapp.net')
                    ? 'phone'
                    : 'other',
            hasAlternateJid: typeof alternateJid === 'string',
        });
    }

    const text = extractText(message.message);

    if (typeof text !== 'string' || !text.trim()) {
        return ignore('no-supported-text', {
            hasMessageContent: Boolean(message.message),
            messageTypes: Object.keys(message.message ?? {}),
        });
    }

    const providerMessageId = message.key.id;

    if (
        typeof providerMessageId !== 'string' ||
        !providerMessageId.trim()
    ) {
        return ignore('missing-provider-message-id');
    }

    try {
        const result = await send({
            provider_message_id: providerMessageId,
            sender_phone_e164: senderPhone,
            text: text.trim(),
        });

        logger.info?.(
            {
                outcome: result?.duplicate ? 'duplicate' : 'forwarded',
                messageIdPresent: true,
            },
            'Inbound WhatsApp message handled.',
        );

        return { ignored: false, result };
    } catch (error) {
        logger.error?.(
            {
                errorName: error?.name ?? 'Error',
                messageIdPresent: true,
            },
            'Failed to forward inbound WhatsApp message to Laravel.',
        );

        throw error;
    }
}
