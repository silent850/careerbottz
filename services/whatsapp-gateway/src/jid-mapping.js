
const lidToPhone = new Map();

function normalizeUserPart(jid, suffix) {
    if (typeof jid !== 'string' || !jid.endsWith(suffix)) {
        return null;
    }

    const user = jid.slice(0, -suffix.length).split(':')[0];

    return user || null;
}

export function normalizeLidJid(jid) {
    const user = normalizeUserPart(jid, '@lid');

    return user ? `${user}@lid` : null;
}

export function extractPhoneNumber(jid) {
    const user = normalizeUserPart(jid, '@s.whatsapp.net');

    return user && /^\d{8,15}$/.test(user)
        ? user
        : null;
}

export function rememberPhoneNumberShare({ lid, jid } = {}) {
    const normalizedLid = normalizeLidJid(lid);
    const phone = extractPhoneNumber(jid);

    if (!normalizedLid || !phone) {
        return false;
    }

    lidToPhone.set(normalizedLid, phone);

    return true;
}

export function resolvePhoneNumberForJid(jid) {
    const directPhone = extractPhoneNumber(jid);

    if (directPhone) {
        return directPhone;
    }

    const normalizedLid = normalizeLidJid(jid);

    if (!normalizedLid) {
        return null;
    }

    return lidToPhone.get(normalizedLid) ?? null;
}
