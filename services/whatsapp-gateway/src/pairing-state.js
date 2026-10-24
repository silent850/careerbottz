
const state = {
    status: 'disconnected',
    qr: null,
    updatedAt: new Date().toISOString(),
};

function updateStatus(status, options = {}) {
    state.status = status;

    if (Object.hasOwn(options, 'qr')) {
        state.qr = options.qr;
    }

    state.updatedAt = new Date().toISOString();

    return getPairingState();
}

function getPairingState() {
    return {
        status: state.status,
        qr: state.qr,
        updatedAt: state.updatedAt,
    };
}

export {
    getPairingState,
    updateStatus,
};
