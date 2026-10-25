
import { timingSafeEqual } from 'node:crypto';
import { rename } from 'node:fs/promises';
import Fastify from 'fastify';
import pino from 'pino';
import makeWASocket, {
    DisconnectReason,
    useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import qrcode from 'qrcode-terminal';

import { config } from './config.js';
import { handleWhatsAppMessage } from './inbound-handler.js';
import { rememberPhoneNumberShare } from './jid-mapping.js';
import {
    getPairingState,
    updateStatus,
} from './pairing-state.js';

const app = Fastify({
    logger: {
        level: config.nodeEnv === 'production' ? 'info' : 'debug',
    },
});

let socket;
let reconnectTimer;
let shuttingDown = false;
let pairingResetInProgress = false;

function authenticateInternalRequest(request, reply, done) {
    const suppliedSecret = request.headers['x-gateway-secret'];

    if (
        typeof suppliedSecret !== 'string' ||
        suppliedSecret.length !== config.gatewaySharedSecret.length
    ) {
        reply.code(401).send({ message: 'Unauthorized.' });
        return;
    }

    const suppliedBuffer = Buffer.from(suppliedSecret, 'utf8');
    const expectedBuffer = Buffer.from(
        config.gatewaySharedSecret,
        'utf8',
    );

    if (!timingSafeEqual(suppliedBuffer, expectedBuffer)) {
        reply.code(401).send({ message: 'Unauthorized.' });
        return;
    }

    done();
}

app.get('/health', async () => ({
    status: 'ok',
    service: 'careerbot-tz-whatsapp-gateway',
    whatsapp: socket?.user ? 'connected' : 'disconnected',
}));

app.get(
    '/internal/pairing-status',
    {
        preHandler: authenticateInternalRequest,
    },
    async () => getPairingState(),
);

function scheduleReconnect(delay = 3000) {
    if (
        reconnectTimer ||
        shuttingDown ||
        pairingResetInProgress
    ) {
        return;
    }

    reconnectTimer = setTimeout(() => {
        reconnectTimer = undefined;

        startWhatsApp().catch((error) => {
            app.log.error(
                { errorName: error?.name ?? 'Error' },
                'WhatsApp reconnection failed.',
            );

            scheduleReconnect(5000);
        });
    }, delay);
}

async function startWhatsApp() {
    if (shuttingDown || pairingResetInProgress || socket) {
        return;
    }

    const { state, saveCreds } = await useMultiFileAuthState(
        config.whatsappSessionDir,
    );

    if (shuttingDown || pairingResetInProgress || socket) {
        return;
    }

    const currentSocket = makeWASocket({
        auth: state,
        logger: pino({ level: 'silent' }),
        browser: ['CareerBotTz', 'Chrome', '1.0.0'],
        retryRequestDelayMs: 5000,
    });

    socket = currentSocket;

    currentSocket.ev.on('creds.update', saveCreds);

    currentSocket.ev.on('chats.phoneNumberShare', (mapping) => {
        const stored = rememberPhoneNumberShare(mapping);

        app.log.info(
            {
                mappingStored: stored,
                lidPresent: typeof mapping?.lid === 'string',
                phoneJidPresent: typeof mapping?.jid === 'string',
            },
            'WhatsApp phone-number sharing event processed.',
        );
    });

    currentSocket.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            updateStatus('waiting_for_qr', { qr });

            app.log.info('A WhatsApp pairing QR code is available.');
            console.log('\nScan this QR code with WhatsApp:');
            qrcode.generate(qr, { small: true });
        }

        if (connection === 'open') {
            updateStatus('connected', { qr: null });
            app.log.info('WhatsApp connection established.');
        }

        if (connection !== 'close') {
            return;
        }

        if (socket === currentSocket) {
            socket = undefined;
        }

        const statusCode =
            lastDisconnect?.error?.output?.statusCode;

        if (statusCode === DisconnectReason.loggedOut) {
            if (pairingResetInProgress || shuttingDown) {
                return;
            }

            pairingResetInProgress = true;
            updateStatus('disconnected', { qr: null });

            app.log.warn(
                'WhatsApp session logged out. Preparing fresh pairing.',
            );

            try {
                const sessionDir = config.whatsappSessionDir;
                const archivedSessionDir =
                    `${sessionDir}.logged-out-${Date.now()}`;

                try {
                    await rename(sessionDir, archivedSessionDir);

                    app.log.info(
                        'Logged-out WhatsApp session archived.',
                    );
                } catch (error) {
                    if (error?.code !== 'ENOENT') {
                        throw error;
                    }

                    app.log.info(
                        'No existing session directory to archive.',
                    );
                }

                pairingResetInProgress = false;
                updateStatus('disconnected', { qr: null });

                app.log.info(
                    'Starting a new WhatsApp pairing attempt.',
                );

                scheduleReconnect(1000);
            } catch (error) {
                pairingResetInProgress = false;

                app.log.error(
                    { errorName: error?.name ?? 'Error' },
                    'Failed to prepare a fresh WhatsApp session.',
                );
            }

            return;
        }

        if (shuttingDown || pairingResetInProgress) {
            return;
        }

        updateStatus('reconnecting', { qr: null });

        app.log.warn(
            { statusCode: statusCode ?? null },
            'WhatsApp connection closed.',
        );

        scheduleReconnect(3000);
    });

    currentSocket.ev.on('messages.upsert', async (event) => {
        if (!Array.isArray(event?.messages)) {
            app.log.warn(
                'Ignoring malformed WhatsApp message event.',
            );
            return;
        }

        if (event.type !== 'notify') {
            return;
        }

        for (const message of event.messages) {
            const remoteJid = message?.key?.remoteJid;

            const chatType =
                typeof remoteJid !== 'string'
                    ? 'unknown'
                    : remoteJid.endsWith('@g.us')
                        ? 'group'
                        : remoteJid.endsWith('@s.whatsapp.net')
                            ? 'direct'
                            : remoteJid.endsWith('@lid')
                                ? 'lid'
                                : 'other';

            app.log.info(
                {
                    chatType,
                    fromMe: Boolean(message?.key?.fromMe),
                    hasMessageContent: Boolean(message?.message),
                    messageTypes: Object.keys(message?.message ?? {}),
                    hasProviderMessageId: Boolean(message?.key?.id),
                    hasAlternateJid: Boolean(message?.key?.remoteJidAlt),
                },
                'WhatsApp inbound message received.',
            );

            try {
                const result = await handleWhatsAppMessage(
                    message,
                    app.log,
                    undefined,
                    async (lid) => {
                        const mappingStore =
                            currentSocket.signalRepository?.lidMapping;

                        if (
                            typeof mappingStore?.getPNForLID !== 'function'
                        ) {
                            return null;
                        }

                        return await mappingStore.getPNForLID(lid);
                    },
                );

                app.log.info(
                    {
                        handlerCompleted: true,
                        ignored: result?.ignored ?? null,
                        reason: result?.reason ?? null,
                        duplicate: result?.result?.duplicate ?? null,
                    },
                    'WhatsApp inbound message processing completed.',
                );
            } catch (error) {
                app.log.error(
                    {
                        errorName: error?.name ?? 'Error',
                    },
                    'WhatsApp inbound message processing failed.',
                );
            }
        }
    });
}

process.on('unhandledRejection', (reason) => {
    const error = reason instanceof Error ? reason : undefined;
    const statusCode = error?.output?.statusCode;
    const message = error?.message ?? String(reason);

    if (
        statusCode === DisconnectReason.connectionClosed &&
        message === 'Connection Closed' &&
        !socket
    ) {
        app.log.warn(
            'A retry operation rejected after the WhatsApp socket closed.',
        );
        return;
    }

    app.log.fatal(
        { errorName: error?.name ?? 'Error', message },
        'Unexpected unhandled promise rejection.',
    );

    process.exitCode = 1;
});

try {
    await app.listen({
        host: '0.0.0.0',
        port: config.port,
    });

    app.log.info(
        `Gateway health endpoint listening on port ${config.port}.`,
    );

    await startWhatsApp();
} catch (error) {
    app.log.error(
        { errorName: error?.name ?? 'Error' },
        'WhatsApp gateway startup failed.',
    );

    process.exit(1);
}

async function shutdown() {
    if (shuttingDown) {
        return;
    }

    shuttingDown = true;

    if (reconnectTimer) {
        clearTimeout(reconnectTimer);
        reconnectTimer = undefined;
    }

    await app.close();
    process.exit(0);
}

process.once('SIGINT', shutdown);
process.once('SIGTERM', shutdown);
