
import { sendInboundMessage } from './laravel-client.js';
import { resolvePhoneNumberForJid } from './jid-mapping.js';

const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

function unwrapContent(message) {
    return message?.ephemeralMessage?.message
        ?? message?.viewOnceMessage?.message
        ?? message?.viewOnceMessageV2?.message
        ?? message?.documentWithCaptionMessage?.message
        ?? message;
}

function extractText(message) {
    const content = unwrapContent(message);

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

function extractAttachment(message) {
    const content = unwrapContent(message);

    if (content?.imageMessage) {
        return {
            media: content.imageMessage,
            mimetype: content.imageMessage.mimetype,
            fileName: 'whatsapp-image',
        };
    }

    if (content?.documentMessage) {
        return {
            media: content.documentMessage,
            mimetype: content.documentMessage.mimetype,
            fileName: content.documentMessage.fileName,
        };
    }

    return null;
}

function normalizePhoneJid(jid) {
    const phone = resolvePhoneNumberForJid(jid);

    return phone ? `+${phone}` : null;
}

function isSupportedAttachment(mimetype) {
    return [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ].includes(String(mimetype ?? '').toLowerCase());
}

function safeFileName(fileName, mimetype) {
    const fallback = mimetype === 'application/pdf'
        ? 'job-advertisement.pdf'
        : 'whatsapp-image';

    const name = typeof fileName === 'string' && fileName.trim()
        ? fileName.trim()
        : fallback;

    return name
        .split(/[\\/]/)
        .pop()
        .replace(/[^a-zA-Z0-9._-]/g, '_')
        .slice(0, 180) || fallback;
}

async function readMediaStream(stream) {
    const chunks = [];
    let totalBytes = 0;

    for await (const chunk of stream) {
        const buffer = Buffer.isBuffer(chunk)
            ? chunk
            : Buffer.from(chunk);

        totalBytes += buffer.length;

        if (totalBytes > MAX_ATTACHMENT_BYTES) {
            throw new Error('attachment-too-large');
        }

        chunks.push(buffer);
    }

    if (totalBytes === 0) {
        throw new Error('empty-attachment');
    }

    return Buffer.concat(chunks, totalBytes);
}

export async function handleWhatsAppMessage(
    message,
    logger = console,
    send = sendInboundMessage,
    resolveLidPhoneNumber = async () => null,
    downloadMedia = null,
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

    if (message.key.fromMe === true) {
        return ignore('message-from-linked-account');
    }

    const remoteJid = message.key.remoteJid;
    const alternateJid = message.key.remoteJidAlt;

    if (typeof remoteJid !== 'string' || !remoteJid.trim()) {
        return ignore('missing-chat-id');
    }

    if (remoteJid.endsWith('@g.us')) {
        return ignore('group-chat-disabled');
    }

    if (
        remoteJid === 'status@broadcast' ||
        remoteJid.endsWith('@broadcast')
    ) {
        return ignore('unsupported-chat-type');
    }

    let senderPhone =
        normalizePhoneJid(remoteJid) ??
        normalizePhoneJid(alternateJid);

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

    if (!senderPhone && typeof alternateJid === 'string') {
        if (alternateJid.endsWith('@lid')) {
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
    const attachmentMessage = extractAttachment(message.message);
    const normalizedText = typeof text === 'string' ? text.trim() : '';

    if (!normalizedText && !attachmentMessage) {
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

    let attachment = null;

    if (attachmentMessage) {
        const mimetype = String(
            attachmentMessage.mimetype ?? '',
        ).toLowerCase();

        if (!isSupportedAttachment(mimetype)) {
            return ignore('unsupported-attachment-type', {
                attachmentType: mimetype || 'unknown',
            });
        }

        if (typeof downloadMedia !== 'function') {
            throw new Error(
                'WhatsApp media downloader is not configured.',
            );
        }

        try {
            const stream = await downloadMedia(message);
            const buffer = await readMediaStream(stream);

            attachment = {
                buffer,
                mimetype,
                fileName: safeFileName(
                    attachmentMessage.fileName,
                    mimetype,
                ),
            };
        } catch (error) {
            if (error?.message === 'attachment-too-large') {
                return ignore('attachment-too-large', {
                    maximumBytes: MAX_ATTACHMENT_BYTES,
                });
            }

            if (error?.message === 'empty-attachment') {
                return ignore('empty-attachment');
            }

            logger.error?.(
                {
                    errorName: error?.name ?? 'Error',
                    messageIdPresent: true,
                },
                'Failed to download inbound WhatsApp media.',
            );

            throw error;
        }
    }

    try {
        const result = await send(
            {
                provider_message_id: providerMessageId,
                sender_phone_e164: senderPhone,
                text: normalizedText,
            },
            attachment,
        );

        logger.info?.(
            {
                outcome: result?.duplicate ? 'duplicate' : 'forwarded',
                messageIdPresent: true,
                hasAttachment: Boolean(attachment),
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
