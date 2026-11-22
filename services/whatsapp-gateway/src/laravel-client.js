
import { config } from './config.js';

const inboundEndpoint = new URL(
    '/api/internal/whatsapp/inbound',
    config.laravelBaseUrl,
).toString();

export async function sendInboundMessage(payload, attachment = null) {
    let headers = {
        Accept: 'application/json',
        'X-Gateway-Secret': config.gatewaySharedSecret,
    };

    let body;

    if (attachment) {
        const form = new FormData();

        form.append('provider_message_id', payload.provider_message_id);
        form.append('sender_phone_e164', payload.sender_phone_e164);
        form.append('text', payload.text ?? '');

        form.append(
            'attachment',
            new Blob([attachment.buffer], {
                type: attachment.mimetype,
            }),
            attachment.fileName,
        );

        body = form;
    } else {
        headers = {
            ...headers,
            'Content-Type': 'application/json',
        };

        body = JSON.stringify(payload);
    }

    let response;

    try {
        response = await fetch(inboundEndpoint, {
            method: 'POST',
            headers,
            body,
            signal: AbortSignal.timeout(30000),
        });
    } catch (error) {
        const reason =
            error?.name === 'TimeoutError'
                ? 'request timed out'
                : 'network connection failed';

        throw new Error(
            `Could not reach the Laravel inbound API: ${reason}.`,
        );
    }

    let result;

    try {
        result = await response.json();
    } catch {
        throw new Error(
            `Laravel returned an invalid JSON response (HTTP ${response.status}).`,
        );
    }

    if (!response.ok) {
        throw new Error(
            `Laravel inbound API rejected the request (HTTP ${response.status}).`,
        );
    }

    return result;
}
