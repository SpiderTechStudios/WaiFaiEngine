<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $portal['brand_color'] }}">
    <title>{{ $portal['business_name'] }}</title>
    <style>
        :root {
            --brand: {{ $portal['brand_color'] }};
            --brand-soft: color-mix(in srgb, var(--brand) 18%, #ffffff);
            --brand-ink: #ffffff;
            --accent: #f97316;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e8edf2;
            --field: #f3f5f7;
            --danger: #b91c1c;
            --ok: #065f46;
            --wait: #9a3412;
            --radius: 18px;
            --radius-sm: 12px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
            color: var(--ink);
            line-height: 1.45;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding: 18px 14px 36px;
            background:
                linear-gradient(
                    180deg,
                    color-mix(in srgb, var(--brand) 55%, #ffffff) 0%,
                    color-mix(in srgb, var(--brand) 12%, #ffffff) 42%,
                    #ffffff 100%
                );
        }
        .portal { width: 100%; max-width: 420px; }
        .shell {
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow:
                0 18px 40px rgba(15, 23, 42, .12),
                0 2px 6px rgba(15, 23, 42, .06);
        }
        .shell-head {
            background: var(--brand);
            color: var(--brand-ink);
            text-align: center;
            padding: 22px 20px 26px;
        }
        .brand-mark {
            width: 56px;
            height: 56px;
            margin: 0 auto 12px;
            border-radius: 14px;
            background: var(--accent);
            display: grid;
            place-items: center;
            box-shadow: 0 8px 18px rgba(0,0,0,.12);
            overflow: hidden;
        }
        .brand-mark img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .brand-mark svg { width: 28px; height: 28px; display: block; color: #111; }
        .shell-head h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .shell-head .welcome {
            margin: 8px auto 0;
            max-width: 28ch;
            font-size: 13px;
            line-height: 1.4;
            opacity: .95;
            font-weight: 500;
        }
        .shell-body { padding: 20px 18px 18px; }
        .block-title {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: 800;
            color: var(--ink);
        }
        .section-label {
            margin: 22px 0 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .field {
            width: 100%;
            padding: 14px 14px;
            font-size: 16px;
            border: 0;
            border-radius: var(--radius-sm);
            background: var(--field);
            color: var(--ink);
        }
        .field:focus {
            outline: none;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 28%, transparent);
        }
        .field + .field { margin-top: 10px; }
        .btn {
            display: block;
            width: 100%;
            text-align: center;
            border: 0;
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform .05s ease, opacity .15s ease;
        }
        .btn:active { transform: scale(.99); }
        .btn:disabled { opacity: .65; cursor: wait; }
        .btn-primary {
            background: var(--brand);
            color: var(--brand-ink);
            margin-top: 12px;
        }
        .btn-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            padding: 9px 16px;
            border: 0;
            border-radius: 999px;
            background: var(--brand);
            color: var(--brand-ink);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
        }
        .btn-pill:active { transform: scale(.98); }
        .packages { display: flex; flex-direction: column; gap: 10px; }
        .package {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            padding: 14px 14px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            text-align: left;
            font: inherit;
            color: inherit;
            cursor: default;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        }
        .package .p-name {
            font-weight: 800;
            font-size: 15px;
            margin: 0;
        }
        .package .p-price {
            margin: 3px 0 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--brand);
        }
        .package .p-meta {
            margin: 2px 0 0;
            font-size: 12px;
            color: var(--muted);
        }
        .package .p-badge {
            display: inline-block;
            margin-top: 4px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: var(--brand);
            background: var(--brand-soft);
            border-radius: 999px;
            padding: 2px 8px;
        }
        .quiet-link {
            display: block;
            width: 100%;
            margin-top: 16px;
            padding: 10px;
            border: 0;
            background: transparent;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .pay-summary {
            background: var(--brand-soft);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 14px;
        }
        .pay-summary .label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--muted);
            margin: 0 0 4px;
        }
        .pay-summary .name {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }
        .pay-summary .price {
            margin: 4px 0 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--brand);
        }
        .field-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 8px;
        }
        .error {
            color: var(--danger);
            font-size: 13px;
            margin: 10px 0 0;
            min-height: 0;
        }
        .status-box {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            font-size: 14px;
            color: var(--wait);
        }
        .status-box.ok {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: var(--ok);
        }
        .status-box .meta {
            margin-top: 6px;
            font-size: 12px;
            opacity: .85;
            word-break: break-all;
        }
        .back {
            display: inline-block;
            margin-top: 14px;
            background: none;
            border: 0;
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            padding: 4px 0;
        }
        .support {
            text-align: center;
            font-size: 13px;
            color: var(--muted);
            margin-top: 16px;
        }
        .support a {
            color: var(--brand);
            font-weight: 700;
            text-decoration: none;
        }
        .hidden { display: none !important; }
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .96);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
            z-index: 50;
        }
        .spinner {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 3px solid color-mix(in srgb, var(--brand) 25%, #fff);
            border-top-color: var(--brand);
            animation: spin .8s linear infinite;
            margin-bottom: 14px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <main class="portal">
        <div class="shell">
            <header class="shell-head">
                <div class="brand-mark">
                    @if (! empty($portal['logo_url']))
                        <img src="{{ $portal['logo_url'] }}" alt="{{ $portal['business_name'] }}">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 18.5a1.25 1.25 0 1 0 0-2.5 1.25 1.25 0 0 0 0 2.5Z" fill="currentColor"/>
                            <path d="M8.2 14.3a5.5 5.5 0 0 1 7.6 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M5.5 11.4a9.5 9.5 0 0 1 13 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M2.8 8.5a13.5 13.5 0 0 1 18.4 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    @endif
                </div>

                <h1>{{ $portal['business_name'] }}</h1>
                <p class="welcome">{{ $portal['welcome_message'] }}</p>
            </header>

            <div class="shell-body">
                {{-- HOME: voucher + packages --}}
                <section id="state-home">
                    <h2 class="block-title">Weka namba ya voucher</h2>
                    <input id="voucherCode" class="field" type="text" inputmode="numeric" autocomplete="off" placeholder="mf. 123456">
                    <input id="voucherPhone" class="field" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Namba ya simu">
                    <p id="voucherError" class="error"></p>
                    <button type="button" id="voucherSubmit" class="btn btn-primary">Unganisha</button>

                    <div class="section-label">Nunua intaneti</div>
                    <div id="packageGrid" class="packages"></div>
                    <p id="subscribeEmpty" class="error hidden">Hakuna vifurushi vinavyopatikana kwa sasa.</p>

                    <button type="button" class="quiet-link" data-go="redeem">Tayari una kifurushi? Endeleza hapa</button>
                </section>

                {{-- PAY: after tapping Lipa --}}
                <section id="state-pay" class="hidden">
                    <div class="pay-summary">
                        <p class="label">Kifurushi</p>
                        <p class="name" id="payPlanName">—</p>
                        <p class="price" id="payPlanPrice">—</p>
                    </div>

                    <label class="field-label" for="subscribePhone">Namba ya simu</label>
                    <input id="subscribePhone" class="field" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Ingiza namba ya simu">

                    <p id="subscribeError" class="error"></p>
                    <div id="subscribeStatus" class="status-box hidden"></div>
                    <button type="button" id="subscribeSubmit" class="btn btn-primary">Lipa sasa</button>
                    <button type="button" class="back" data-back>&larr; Rudi nyuma</button>
                </section>

                {{-- REDEEM: continue existing package --}}
                <section id="state-redeem" class="hidden">
                    <h2 class="block-title">Endeleza kifurushi</h2>
                    <label class="field-label" for="redeemPhone">Namba ya simu</label>
                    <input id="redeemPhone" class="field" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Ingiza namba ya simu">
                    <p id="redeemError" class="error"></p>
                    <button type="button" id="redeemSubmit" class="btn btn-primary">Endelea</button>
                    <button type="button" class="back" data-back>&larr; Rudi nyuma</button>
                </section>
            </div>
        </div>

        <p class="support">
            Kwa msaada, wasiliana nasi:
            <a href="tel:{{ $portal['contact_phone'] }}">{{ $portal['contact_phone'] }}</a>
        </p>
    </main>

    <div id="overlay" class="overlay hidden">
        <div class="spinner"></div>
        <strong id="overlayText">Inaendelea...</strong>
    </div>

    <script>
        const PORTAL = @json($portal);
        const durationLabels = { HOURS: 'Masaa', DAYS: 'Siku', WEEKS: 'Wiki', MONTHS: 'Miezi', UNLIMITED_DATA: 'Bila kikomo' };

        let selectedPlanId = null;
        let state = 'home';

        const els = {
            home: document.getElementById('state-home'),
            pay: document.getElementById('state-pay'),
            redeem: document.getElementById('state-redeem'),
            grid: document.getElementById('packageGrid'),
            empty: document.getElementById('subscribeEmpty'),
            subscribePhone: document.getElementById('subscribePhone'),
            subscribeError: document.getElementById('subscribeError'),
            subscribeStatus: document.getElementById('subscribeStatus'),
            subscribeSubmit: document.getElementById('subscribeSubmit'),
            payPlanName: document.getElementById('payPlanName'),
            payPlanPrice: document.getElementById('payPlanPrice'),
            voucherCode: document.getElementById('voucherCode'),
            voucherPhone: document.getElementById('voucherPhone'),
            voucherError: document.getElementById('voucherError'),
            voucherSubmit: document.getElementById('voucherSubmit'),
            redeemPhone: document.getElementById('redeemPhone'),
            redeemError: document.getElementById('redeemError'),
            redeemSubmit: document.getElementById('redeemSubmit'),
            overlay: document.getElementById('overlay'),
            overlayText: document.getElementById('overlayText'),
        };

        const states = { home: els.home, pay: els.pay, redeem: els.redeem };

        function setState(next) {
            state = next;
            Object.keys(states).forEach((key) => states[key].classList.toggle('hidden', key !== next));
            if (next === 'home') {
                resetPay();
                resetRedeem();
            }
        }

        function setError(el, message) { el.textContent = message || ''; }
        function setBusy(button, busy) {
            button.disabled = busy;
            if (!button.dataset.label) button.dataset.label = button.textContent;
            button.textContent = busy ? 'Inaendelea...' : button.dataset.label;
        }
        function showOverlay(text) { els.overlayText.textContent = text || 'Inaendelea...'; els.overlay.classList.remove('hidden'); }
        function hideOverlay() { els.overlay.classList.add('hidden'); }

        function normalizePhone(raw) {
            let value = String(raw || '').replace(/[\s\-()]/g, '');
            if (value.startsWith('+')) value = value.slice(1);
            if (/^255\d{9}$/.test(value)) return '0' + value.slice(3);
            if (/^0\d{9}$/.test(value)) return value;
            if (/^[67]\d{8}$/.test(value)) return '0' + value;
            return null;
        }

        function phoneError(raw) {
            if (!String(raw || '').trim()) return 'Namba ya simu haijawekwa.';
            return 'Ingiza namba sahihi ya simu.';
        }

        function formatPrice(value) {
            const amount = Number(value || 0);
            return 'TZS ' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(amount);
        }

        function durationText(pkg) {
            if (!pkg.duration) return durationLabels[pkg.duration_unit] || 'Bila kikomo';
            const unit = durationLabels[pkg.duration_unit] || pkg.duration_unit;
            return pkg.duration + ' ' + unit;
        }

        async function api(method, url, body) {
            const response = await fetch(url, {
                method,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined,
            });
            let json = {};
            try { json = await response.json(); } catch (e) {}
            if (!response.ok) {
                const error = new Error((json && json.message) || 'Request failed');
                error.payload = json;
                throw error;
            }
            return json;
        }

        function errorMessage(error) {
            const payload = error && error.payload;
            const message = (payload && payload.message) || '';
            if (/too many attempts/i.test(message)) {
                return 'Unafanya maombi mengi sana. Subiri kidogo kisha jaribu tena.';
            }
            if (payload && payload.data && typeof payload.data === 'object') {
                const first = Object.values(payload.data)[0];
                if (Array.isArray(first) && first[0]) return first[0];
                if (typeof first === 'string') return first;
            }
            return message || 'Kuna hitilafu. Tafadhali jaribu tena.';
        }

        function goToGateway(url) {
            showOverlay('Inakuelekeza kwenye intaneti...');
            window.location.href = url;
        }

        function fillPaySummary(pkg) {
            els.payPlanName.textContent = pkg.name;
            els.payPlanPrice.textContent = formatPrice(pkg.price) + ' · ' + durationText(pkg);
        }

        function renderPackages() {
            els.grid.innerHTML = '';
            const packages = PORTAL.packages || [];

            if (!packages.length) {
                els.empty.classList.remove('hidden');
                return;
            }
            els.empty.classList.add('hidden');

            packages.forEach((pkg) => {
                const row = document.createElement('div');
                row.className = 'package';
                row.dataset.id = pkg.id;

                const info = document.createElement('div');
                const name = document.createElement('p');
                name.className = 'p-name';
                name.textContent = pkg.name;
                info.appendChild(name);

                const price = document.createElement('p');
                price.className = 'p-price';
                price.textContent = formatPrice(pkg.price);
                info.appendChild(price);

                const meta = document.createElement('p');
                meta.className = 'p-meta';
                meta.textContent = durationText(pkg);
                info.appendChild(meta);

                if (pkg.badge) {
                    const badge = document.createElement('span');
                    badge.className = 'p-badge';
                    badge.textContent = pkg.badge;
                    info.appendChild(badge);
                }

                const payBtn = document.createElement('button');
                payBtn.type = 'button';
                payBtn.className = 'btn-pill';
                payBtn.textContent = 'Lipa';
                payBtn.addEventListener('click', () => openPay(pkg));

                row.appendChild(info);
                row.appendChild(payBtn);
                els.grid.appendChild(row);
            });
        }

        function openPay(pkg) {
            selectedPlanId = pkg.id;
            fillPaySummary(pkg);
            setError(els.subscribeError, '');
            setPaymentStatus('');
            els.subscribePhone.value = '';
            setState('pay');
        }

        function resetPay() {
            selectedPlanId = null;
            els.subscribePhone.value = '';
            setError(els.subscribeError, '');
            setPaymentStatus('');
        }
        function resetRedeem() { els.redeemPhone.value = ''; setError(els.redeemError, ''); }

        function setPaymentStatus(html, kind) {
            if (!html) {
                els.subscribeStatus.className = 'status-box hidden';
                els.subscribeStatus.innerHTML = '';
                return;
            }
            els.subscribeStatus.className = 'status-box ' + (kind || '');
            els.subscribeStatus.innerHTML = html;
        }

        async function submitSubscribe() {
            if (!selectedPlanId) { setError(els.subscribeError, 'Chagua kifurushi kwanza.'); return; }
            const phone = normalizePhone(els.subscribePhone.value);
            if (phone === null) { setError(els.subscribeError, phoneError(els.subscribePhone.value)); return; }
            setError(els.subscribeError, '');
            setPaymentStatus('');

            setBusy(els.subscribeSubmit, true);
            try {
                const result = await api('POST', '/api/v1/portal/' + PORTAL.subdomain + '/payments', {
                    internet_plan_id: selectedPlanId,
                    customer_phone: phone,
                    captive_session: PORTAL.session_token || null,
                });
                const payment = result.data || {};
                const pushHint = payment.ussd_message
                    || 'Ombi la malipo limetumwa kwenye simu yako. Thibitisha kwenye simu.';
                setPaymentStatus(
                    '<strong>Inasubiri malipo...</strong><div>' + escapeHtml(pushHint) + '</div>' +
                    (payment.reference ? '<div class="meta">Ref: ' + escapeHtml(payment.reference) + '</div>' : ''),
                    ''
                );
                showOverlay('Thibitisha malipo kwenye simu...');
                pollPayment(payment.id);
            } catch (error) {
                setBusy(els.subscribeSubmit, false);
                hideOverlay();
                setError(els.subscribeError, errorMessage(error));
            }
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        async function pollPayment(paymentId) {
            for (let attempt = 0; attempt < 90; attempt++) {
                await new Promise((resolve) => setTimeout(resolve, 4000));
                try {
                    const result = await api('GET', '/api/v1/portal/' + PORTAL.subdomain + '/payments/' + paymentId);
                    const payment = result.data;

                    if (payment.status === 'failed') {
                        hideOverlay();
                        setBusy(els.subscribeSubmit, false);
                        setPaymentStatus('');
                        setError(els.subscribeError, 'Malipo yameshindikana. Tafadhali jaribu tena.');
                        return;
                    }
                    if (payment.next_action === 'open_gateway_auth_url' && payment.gateway_auth_url) {
                        setPaymentStatus(
                            '<strong>Malipo yamefanikiwa.</strong><div>Inakufungulia intaneti...</div>' +
                            (payment.reference ? '<div class="meta">Ref: ' + escapeHtml(payment.reference) + '</div>' : ''),
                            'ok'
                        );
                        goToGateway(payment.gateway_auth_url);
                        return;
                    }
                    if (payment.status === 'paid') {
                        hideOverlay();
                        setBusy(els.subscribeSubmit, false);
                        setPaymentStatus(
                            '<strong>Malipo yamefanikiwa.</strong><div>Inakufungulia intaneti...</div>' +
                            (payment.reference ? '<div class="meta">Ref: ' + escapeHtml(payment.reference) + '</div>' : ''),
                            'ok'
                        );
                        if (payment.gateway_auth_url) {
                            goToGateway(payment.gateway_auth_url);
                            return;
                        }
                        showOverlay('Malipo yamefanikiwa. Inakufungulia intaneti...');
                        return;
                    }

                    setPaymentStatus(
                        '<strong>Inasubiri uthibitisho...</strong><div>Tafadhali thibitisha kwenye simu, usifunge ukurasa huu.</div>' +
                        (payment.reference ? '<div class="meta">Ref: ' + escapeHtml(payment.reference) + '</div>' : ''),
                        ''
                    );
                } catch (error) {
                    // keep polling through transient errors
                }
            }
            hideOverlay();
            setBusy(els.subscribeSubmit, false);
            setError(els.subscribeError, 'Malipo hayajathibitishwa bado. Tafadhali jaribu tena.');
        }

        async function submitVoucher() {
            const code = els.voucherCode.value.trim();
            if (!code) { setError(els.voucherError, 'Ingiza namba ya voucher.'); return; }
            const phone = normalizePhone(els.voucherPhone.value);
            if (phone === null) { setError(els.voucherError, phoneError(els.voucherPhone.value)); return; }
            setError(els.voucherError, '');

            setBusy(els.voucherSubmit, true);
            try {
                const result = await api('POST', '/api/v1/portal/' + PORTAL.subdomain + '/vouchers/redeem', {
                    code: code,
                    customer_name: 'Mteja',
                    customer_phone: phone,
                    captive_session: PORTAL.session_token || null,
                    mac_address: PORTAL.client_mac || null,
                });
                const url = result.data && result.data.captive ? result.data.captive.gateway_auth_url : null;
                if (url) { goToGateway(url); return; }
                hideOverlay();
                setBusy(els.voucherSubmit, false);
                setError(els.voucherError, 'Vocha imetumika. Tafadhali unganisha kifaa chako kwenye WiFi.');
            } catch (error) {
                setBusy(els.voucherSubmit, false);
                setError(els.voucherError, errorMessage(error));
            }
        }

        async function submitRedeem() {
            const phone = normalizePhone(els.redeemPhone.value);
            if (phone === null) { setError(els.redeemError, phoneError(els.redeemPhone.value)); return; }
            setError(els.redeemError, '');

            setBusy(els.redeemSubmit, true);
            try {
                const restored = await api('POST', '/api/v1/portal/' + PORTAL.subdomain + '/restore', {
                    customer_phone: phone,
                });
                const grantId = restored.data && restored.data.access_grant ? restored.data.access_grant.id : null;

                if (!grantId) {
                    setBusy(els.redeemSubmit, false);
                    setError(els.redeemError, 'Hakuna kifurushi kinachoendelea kwa namba hii.');
                    return;
                }

                if (!PORTAL.session_token) {
                    setBusy(els.redeemSubmit, false);
                    setError(els.redeemError, 'Kifurushi kimepatikana. Tafadhali unganisha kifaa chako kwenye WiFi.');
                    return;
                }

                const authorized = await api('POST', '/api/v1/captive/sessions/' + PORTAL.session_token + '/authorize', {
                    access_grant_id: grantId,
                });
                const url = authorized.data ? authorized.data.gateway_auth_url : null;
                if (url) { goToGateway(url); return; }

                setBusy(els.redeemSubmit, false);
                setError(els.redeemError, 'Imeshindikana kuendeleza kifurushi. Tafadhali jaribu tena.');
            } catch (error) {
                setBusy(els.redeemSubmit, false);
                setError(els.redeemError, errorMessage(error));
            }
        }

        document.querySelectorAll('[data-go]').forEach((button) => {
            button.addEventListener('click', () => setState(button.dataset.go));
        });
        document.querySelectorAll('[data-back]').forEach((button) => {
            button.addEventListener('click', () => setState('home'));
        });
        els.subscribeSubmit.addEventListener('click', submitSubscribe);
        els.voucherSubmit.addEventListener('click', submitVoucher);
        els.redeemSubmit.addEventListener('click', submitRedeem);

        renderPackages();
        setState('home');
    </script>
</body>
</html>
