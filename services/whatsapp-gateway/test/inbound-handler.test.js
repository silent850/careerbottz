import test from 'node:test';
import assert from 'node:assert/strict';

import { handleWhatsAppMessage } from '../src/inbound-handler.js';

function makeMessage(overrides = {}) {
    return {
        key: {
            id: 'wamid.test-001',
            remoteJid: '255712345678@s.whatsapp.net',
            fromMe: false,
        },
        message: {
            conversation: 'Hello CareerBotTz',
        },
        ...overrides,
    };
}

test('forwards a valid inbound text message', async () => {
    let receivedPayload;

    const result = await handleWhatsAppMessage(
        makeMessage(),
        { info() {}, warn() {}, error() {} },
        async (payload) => {
            receivedPayload = payload;
            return { data: { duplicate: false } };
        },
    );

    assert.equal(result.ignored, false);
    assert.deepEqual(receivedPayload, {
        provider_message_id: 'wamid.test-001',
        sender_phone_e164: '+255712345678',
        text: 'Hello CareerBotTz',
    });
});

test('ignores messages sent by the bot itself', async () => {
    const message = makeMessage({
        key: {
            id: 'wamid.test-002',
            remoteJid: '255712345678@s.whatsapp.net',
            fromMe: true,
        },
    });

    const result = await handleWhatsAppMessage(
        message,
        { info() {}, warn() {}, error() {} },
        async () => assert.fail('Sender must not be called'),
    );

    assert.equal(result.ignored, true);
    assert.equal(result.reason, 'message-from-linked-account');
});

test('ignores group messages', async () => {
    const message = makeMessage({
        key: {
            id: 'wamid.test-003',
            remoteJid: '123456789@g.us',
            fromMe: false,
        },
    });

    const result = await handleWhatsAppMessage(
        message,
        { info() {}, warn() {}, error() {} },
        async () => assert.fail('Sender must not be called'),
    );

    assert.equal(result.ignored, true);
    assert.equal(result.reason, 'group-chat-disabled');
});

test('ignores messages without supported text', async () => {
    const message = makeMessage({
        message: { audioMessage: {} },
    });

    const result = await handleWhatsAppMessage(
        message,
        { info() {}, warn() {}, error() {} },
        async () => assert.fail('Sender must not be called'),
    );

    assert.equal(result.ignored, true);
    assert.equal(result.reason, 'no-supported-text');
});

test('propagates Laravel forwarding errors', async () => {
    await assert.rejects(
        handleWhatsAppMessage(
            makeMessage(),
            { info() {}, warn() {}, error() {} },
            async () => {
                throw new Error('Laravel unavailable');
            },
        ),
        /Laravel unavailable/,
    );
});

test('forwards a mapped LID using its resolved phone number', async () => {
    const { rememberPhoneNumberShare } =
        await import('../src/jid-mapping.js');

    const lid = 'handler-test-user@lid';

    assert.equal(
        rememberPhoneNumberShare({
            lid,
            jid: '255712345678@s.whatsapp.net',
        }),
        true,
    );

    let receivedPayload;

    const result = await handleWhatsAppMessage(
        makeMessage({
            key: {
                id: 'wamid.test-lid-001',
                remoteJid: lid,
                fromMe: false,
            },
        }),
        { info() {}, warn() {}, error() {} },
        async (payload) => {
            receivedPayload = payload;
            return { duplicate: false };
        },
    );

    assert.equal(result.ignored, false);
    assert.equal(receivedPayload.sender_phone_e164, '+255712345678');
});

test('uses Baileys mapping store for an unmapped LID', async () => {
    let receivedPayload;
    let requestedLid;

    const lid = 'baileys-store-test-user@lid';

    const result = await handleWhatsAppMessage(
        makeMessage({
            key: {
                id: 'wamid.test-lid-store-001',
                remoteJid: lid,
                fromMe: false,
            },
        }),
        { info() {}, warn() {}, error() {} },
        async (payload) => {
            receivedPayload = payload;
            return { duplicate: false };
        },
        async (requested) => {
            requestedLid = requested;
            return '255712345678:3@s.whatsapp.net';
        },
    );

    assert.equal(requestedLid, lid);
    assert.equal(result.ignored, false);
    assert.equal(receivedPayload.sender_phone_e164, '+255712345678');
});

test('uses remoteJidAlt when it contains the phone-number JID', async () => {
    let receivedPayload;

    const result = await handleWhatsAppMessage(
        makeMessage({
            key: {
                id: 'wamid.test-lid-alt-001',
                remoteJid: 'alternate-test-user@lid',
                remoteJidAlt: '255712345678:3@s.whatsapp.net',
                fromMe: false,
            },
        }),
        { info() {}, warn() {}, error() {} },
        async (payload) => {
            receivedPayload = payload;
            return { duplicate: false };
        },
        async () => {
            assert.fail('Mapping store should not be needed');
        },
    );

    assert.equal(result.ignored, false);
    assert.equal(receivedPayload.sender_phone_e164, '+255712345678');
});

test('ignores an unmapped LID without forwarding to Laravel', async () => {
    const result = await handleWhatsAppMessage(
        makeMessage({
            key: {
                id: 'wamid.test-lid-002',
                remoteJid: 'unmapped-handler-test-user@lid',
                fromMe: false,
            },
        }),
        { info() {}, warn() {}, error() {} },
        async () => assert.fail('An unmapped LID must not be forwarded'),
        async () => null,
    );

    assert.deepEqual(result, {
        ignored: true,
        reason: 'unmapped-lid',
    });
});

test('ignores an invalid phone-number mapping result', async () => {
    const result = await handleWhatsAppMessage(
        makeMessage({
            key: {
                id: 'wamid.test-lid-invalid-001',
                remoteJid: 'invalid-result-test-user@lid',
                fromMe: false,
            },
        }),
        { info() {}, warn() {}, error() {} },
        async () => assert.fail('Invalid mapping must not be forwarded'),
        async () => 'not-a-phone-number',
    );

    assert.deepEqual(result, {
        ignored: true,
        reason: 'unmapped-lid',
    });
});