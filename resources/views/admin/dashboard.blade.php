<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CareerBotTz Admin Dashboard</title>
    @vite(['resources/js/app.js'])

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        header {
            background: #111827;
            color: white;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        main {
            max-width: 900px;
            margin: 32px auto;
            padding: 0 16px;
        }

        .card {
            background: white;
            padding: 24px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
        }

        .status { font-weight: bold; text-transform: capitalize; }
        .muted { color: #6b7280; }

        #qr-container {
            margin-top: 20px;
            text-align: center;
        }

        #qr-container canvas,
        #qr-container img {
            max-width: 100%;
            height: auto;
        }

        button {
            padding: 10px 14px;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }

        button:disabled { opacity: .6; cursor: wait; }
        .logout { background: #374151; color: white; }
        #refresh { background: #1d4ed8; color: white; }
        .error { color: #b91c1c; }

        .warning {
            background: #fffbeb;
            border: 1px solid #f59e0b;
            padding: 12px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
<header>
    <div>
        <strong>CareerBotTz</strong>
        <span> / Administration</span>
    </div>

    <form method="POST" action="{{ route('admin.logout') }}">
        @csrf
        <button class="logout" type="submit">Log out</button>
    </form>
</header>

<main>
    <section class="card">
        <h1>WhatsApp Connection</h1>

        <p>Gateway: <span id="gateway-status" class="status">Checking…</span></p>
        <p>WhatsApp: <span id="whatsapp-status" class="status">Checking…</span></p>
        <p id="last-updated" class="muted">Checking connection status…</p>
        <p id="next-refresh" class="muted">Automatic refresh every 60 seconds.</p>
        <p id="error-message" class="error" role="status"></p>

        <button id="refresh" type="button">Refresh now</button>
    </section>

    <section class="card">
        <h2>Pair WhatsApp</h2>
        <p id="pairing-message">Checking pairing status…</p>
        <div id="qr-container" hidden></div>

        <p class="warning" id="qr-warning" hidden>
            This QR code is sensitive. Scan it only with the intended
            WhatsApp account. Never share or publish a screenshot of it.
        </p>
    </section>
</main>

<script>
(() => {
    'use strict';

    const statusUrl = @json(route('admin.whatsapp.status'));
    const refreshIntervalMs = 60_000;

    const gatewayElement = document.getElementById('gateway-status');
    const whatsappElement = document.getElementById('whatsapp-status');
    const updatedElement = document.getElementById('last-updated');
    const countdownElement = document.getElementById('next-refresh');
    const errorElement = document.getElementById('error-message');
    const pairingElement = document.getElementById('pairing-message');
    const qrContainer = document.getElementById('qr-container');
    const qrWarning = document.getElementById('qr-warning');
    const refreshButton = document.getElementById('refresh');

    let requestInProgress = false;
    let previousQr = null;
    let nextRefreshAt = Date.now() + refreshIntervalMs;

    function clearQr() {
        qrContainer.replaceChildren();
        qrContainer.hidden = true;
        qrWarning.hidden = true;
        previousQr = null;
    }

    async function refreshStatus() {
        if (requestInProgress) return;

        requestInProgress = true;
        refreshButton.disabled = true;
        errorElement.textContent = '';

        try {
            const response = await fetch(statusUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message || 'Unable to retrieve connection status.'
                );
            }

            gatewayElement.textContent = data.gateway || 'unknown';

            const status = data.whatsapp || 'unknown';
            whatsappElement.textContent = status.replaceAll('_', ' ');

            updatedElement.textContent = data.updated_at
                ? 'Gateway status updated: ' +
                  new Date(data.updated_at).toLocaleString()
                : 'Status checked: ' + new Date().toLocaleString();

            if (status === 'connected') {
                pairingElement.textContent =
                    'WhatsApp is connected. No pairing QR is needed.';
                clearQr();
            } else if (data.qr && status === 'waiting_for_qr') {
                pairingElement.textContent =
                    'Scan this QR code with the WhatsApp account you want to connect.';

                if (data.qr !== previousQr) {
                    clearQr();

                    const qrLibrary = window.CareerBotQRCode;

                    if (!qrLibrary || typeof qrLibrary.toCanvas !== 'function') {
                        throw new Error(
                            'Local QR library is unavailable. Check the Vite build.'
                        );
                    }

                    const canvas = document.createElement('canvas');

                    await qrLibrary.toCanvas(canvas, data.qr, {
                        width: 280,
                        margin: 2
                    });

                    qrContainer.replaceChildren(canvas);
                    qrContainer.hidden = false;
                    qrWarning.hidden = false;
                    previousQr = data.qr;
                }
            } else {
                clearQr();

                pairingElement.textContent = status === 'reconnecting'
                    ? 'WhatsApp is reconnecting. Waiting for the next update…'
                    : 'No pairing QR is available yet. The status will be checked again automatically.';
            }
        } catch (error) {
            errorElement.textContent =
                error.message || 'Unable to check connection status.';
            pairingElement.textContent =
                'The status or QR display could not be updated.';

            if (gatewayElement.textContent === 'Checking…') {
                gatewayElement.textContent = 'unknown';
            }
        } finally {
            requestInProgress = false;
            refreshButton.disabled = false;
            nextRefreshAt = Date.now() + refreshIntervalMs;
        }
    }

    function updateCountdown() {
        const secondsLeft = Math.max(
            0,
            Math.ceil((nextRefreshAt - Date.now()) / 1000)
        );

        countdownElement.textContent =
            `Automatic refresh in ${secondsLeft} seconds.`;
    }

    refreshButton.addEventListener('click', refreshStatus);

    refreshStatus();
    setInterval(refreshStatus, refreshIntervalMs);
    setInterval(updateCountdown, 1000);
    updateCountdown();
})();
</script>
</body>
</html>
