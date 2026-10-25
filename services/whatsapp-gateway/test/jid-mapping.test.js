import test from 'node:test';
import assert from 'node:assert/strict';

import {
    rememberPhoneNumberShare,
    resolvePhoneNumberForJid,
} from '../src/jid-mapping.js';

test('stores and resolves a valid LID phone-number mapping', () => {
    const lid = 'test-user-001@lid';
    const jid = '255712345678@s.whatsapp.net';

    assert.equal(rememberPhoneNumberShare({ lid, jid }), true);
    assert.equal(resolvePhoneNumberForJid(lid), '255712345678');
});

test('does not resolve an unmapped LID', () => {
    assert.equal(
        resolvePhoneNumberForJid('unknown-user-002@lid'),
        null,
    );
});

test('rejects an invalid phone-number-sharing mapping', () => {
    assert.equal(
        rememberPhoneNumberShare({
            lid: 'test-user-003@lid',
            jid: 'not-a-phone-number@s.whatsapp.net',
        }),
        false,
    );
});

test('resolves a valid regular WhatsApp phone-number JID', () => {
    assert.equal(
        resolvePhoneNumberForJid('255712345678@s.whatsapp.net'),
        '255712345678',
    );
});

test('does not resolve unsupported JID formats', () => {
    assert.equal(
        resolvePhoneNumberForJid('123456789@g.us'),
        null,
    );
});
