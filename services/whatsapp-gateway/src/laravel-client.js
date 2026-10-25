
import { config } from './config.js';

const inboundEndpoint = new URL(
    '/api/internal/whatsapp/inbound',
    config.laravelBaseUrl,
).toString();

export async function sendInboundMessage(payload) {
    let response;

    try {
        response = await fetch(inboundEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Gateway-Secret': config.gatewaySharedSecret,
            },
            body: JSON.stringify(payload),
            signal: AbortSignal.timeout(10000),
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
