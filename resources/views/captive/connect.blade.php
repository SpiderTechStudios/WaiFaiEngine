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
            --brand-ink: #ffffff;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --bg: #f1f5f9;
            --danger: #b91c1c;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding: 20px 16px 40px;
        }
        .portal { width: 100%; max-width: 480px; }
        .brand-head { text-align: center; margin-bottom: 18px; }
        .brand-head h1 { font-size: 24px; margin: 0 0 6px; letter-spacing: -0.01em; }
        .brand-head p { margin: 0; color: var(--muted); font-size: 14px; }
        .router-note { text-align: center; color: var(--muted); font-size: 12px; margin-top: 6px; }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
        }
        .stack > * { margin: 0; }
        .stack > * + * { margin-top: 12px; }
        .btn {
            display: block;
            width: 100%;
            text-align: center;
            border: 0;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform .05s ease, opacity .15s ease;
        }
        .btn:active { transform: scale(.99); }
        .btn-primary {
            background: var(--brand);
            color: var(--brand-ink);
        }
        .btn-outline {
            background: #fff;
            color: var(--brand);
            border: 2px solid var(--brand);
        }
        .btn[disabled] { opacity: .6; cursor: not-allowed; }
        .support {
            text-align: center;
            font-size: 13px;
            color: var(--muted);
            margin-top: 18px;
        }
        .support a { color: var(--brand); font-weight: 600; text-decoration: none; }
        h2.section-title { font-size: 18px; margin: 0 0 14px; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        .package {
            text-align: left;
            background: #fff;
            border: 2px solid var(--line);
            border-radius: 14px;
            padding: 12px;
            cursor: pointer;
            font: inherit;
            color: inherit;
            position: relative;
        }
        .package.selected {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent);
        }
        .package .p-name { font-weight: 700; font-size: 15px; }
        .package .p-desc { color: var(--muted); font-size: 12px; margin-top: 4px; }
        .package .p-meta { color: var(--muted); font-size: 12px; margin-top: 6px; }
        .package .p-price { margin-top: 8px; font-weight: 700; color: var(--brand); font-size: 16px; }
        .package .p-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .02em;
            background: color-mix(in srgb, var(--brand) 14%, #fff);
            color: var(--brand);
            border-radius: 999px;
            padding: 2px 8px;
            margin-top: 4px;
        }
        label.field-label { display: block; font-size: 13px; font-weight: 600; margin: 14px 0 6px; }
        input[type=tel], input[type=text] {
            width: 100%;
            padding: 13px 12px;
            font-size: 16px;
            border: 1.5px solid var(--line);
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
        }
        input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 15%, transparent); }
        .error { color: var(--danger); font-size: 13px; margin: 10px 0 0; min-height: 0; }
        .back {
            display: inline-block;
            margin-top: 16px;
            background: none;
            border: 0;
            color: var(--muted);
            font-size: 14px;
            cursor: pointer;
            padding: 4px 0;
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
            width: 30px; height: 30px; border-radius: 50%;
            border: 3px solid color-mix(in srgb, var(--brand) 25%, #fff);
            border-top-color: var(--brand);
            animation: spin .8s linear infinite;
            margin-bottom: 14px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (min-width: 560px) {
            .portal { max-width: 640px; }
            .grid { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
        }
    </style>
</head>
<body>
    <main class="portal">
        <header class="brand-head">
            <h1>{{ $portal['business_name'] }}</h1>
            <p>{{ $portal['welcome_message'] }}</p>
            @if (! empty($portal['router_name']))
                <div class="router-note">{{ $portal['router_name'] }}</div>
            @endif
        </header>

        {{-- INITIAL STATE --}}
        <section id="state-initial" class="card stack">
            <button type="button" class="btn btn-primary" data-go="subscribe">Jiunge sasa</button>
            <button type="button" class="btn btn-outline" data-go="voucher">Jiunge kwa vocha</button>
            <button type="button" class="btn btn-outline" data-go="redeem">Endeleza kifurushi</button>
        </section>

        {{-- SUBSCRIBE STATE --}}
        <section id="state-subscribe" class="card hidden">
            <h2 class="section-title">Chagua kifurushi</h2>
            <div id="packageGrid" class="grid"></div>
            <p id="subscribeEmpty" class="error hidden">Hakuna vifurushi vinavyopatikana kwa sasa.</p>

            <label class="field-label" for="subscribePhone">Number ya simu</label>
            <input id="subscribePhone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Ingiza namba ya simu">

            <p id="subscribeError" class="error"></p>
            <button type="button" id="subscribeSubmit" class="btn btn-primary" style="margin-top:14px">Endelea</button>
            <button type="button" class="back" data-back>&larr; Rudi nyuma</button>
        </section>

        {{-- VOUCHER STATE --}}
        <section id="state-voucher" class="card hidden">
            <h2 class="section-title">Jiunge kwa vocha</h2>

            <label class="field-label" for="voucherCode">Namba ya vocha</label>
            <input id="voucherCode" type="text" inputmode="numeric" autocomplete="off" placeholder="Ingiza namba ya vocha">

            <label class="field-label" for="voucherPhone">Number ya simu</label>
            <input id="voucherPhone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Ingiza namba ya simu">

            <p id="voucherError" class="error"></p>
            <button type="button" id="voucherSubmit" class="btn btn-primary" style="margin-top:14px">Endelea</button>
            <button type="button" class="back" data-back>&larr; Rudi nyuma</button>
        </section>

        {{-- REDEEM STATE --}}
        <section id="state-redeem" class="card hidden">
            <h2 class="section-title">Endeleza kifurushi</h2>

            <label class="field-label" for="redeemPhone">Number ya simu</label>
            <input id="redeemPhone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Ingiza namba ya simu">

            <p id="redeemError" class="error"></p>
            <button type="button" id="redeemSubmit" class="btn btn-primary" style="margin-top:14px">Endelea</button>
            <button type="button" class="back" data-back>&larr; Rudi nyuma</button>
        </section>

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

        const els = {
            initial: document.getElementById('state-initial'),
            subscribe: document.getElementById('state-subscribe'),
            voucher: document.getElementById('state-voucher'),
            redeem: document.getElementById('state-redeem'),
            grid: document.getElementById('packageGrid'),
            empty: document.getElementById('subscribeEmpty'),
            subscribePhone: document.getElementById('subscribePhone'),
            subscribeError: document.getElementById('subscribeError'),
            subscribeSubmit: document.getElementById('subscribeSubmit'),
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

        const states = { initial: els.initial, subscribe: els.subscribe, voucher: els.voucher, redeem: els.redeem };
        const durationLabels = { HOURS: 'Saa', DAYS: 'Siku', WEEKS: 'Wiki', MONTHS: 'Miezi', UNLIMITED_DATA: 'Bila kikomo' };

        let selectedPlanId = null;
        let state = 'initial';

        function setState(next) {
            state = next;
            Object.keys(states).forEach((key) => states[key].classList.toggle('hidden', key !== next));
            if (next === 'initial') {
                resetSubscribe();
                resetVoucher();
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
            return 'TSh ' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(amount);
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
            if (payload && payload.data && typeof payload.data === 'object') {
                const first = Object.values(payload.data)[0];
                if (Array.isArray(first) && first[0]) return first[0];
                if (typeof first === 'string') return first;
            }
            return (payload && payload.message) || 'Kuna hitilafu. Tafadhali jaribu tena.';
        }

        function goToGateway(url) {
            showOverlay('Inakuelekeza kwenye intaneti...');
            window.location.href = url;
        }

        // ---- package rendering ----
        function renderPackages() {
            els.grid.innerHTML = '';
            const packages = PORTAL.packages || [];

            if (!packages.length) {
                els.empty.classList.remove('hidden');
                return;
            }
            els.empty.classList.add('hidden');

            packages.forEach((pkg) => {
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'package';
                card.dataset.id = pkg.id;
                card.innerHTML =
                    '<div class="p-name"></div>' +
                    (pkg.badge ? '<div class="p-badge"></div>' : '') +
                    (pkg.description ? '<div class="p-desc"></div>' : '') +
                    '<div class="p-meta"></div>' +
                    '<div class="p-price"></div>';
                card.querySelector('.p-name').textContent = pkg.name;
                if (pkg.badge) card.querySelector('.p-badge').textContent = pkg.badge;
                if (pkg.description) card.querySelector('.p-desc').textContent = pkg.description;
                card.querySelector('.p-meta').textContent = durationText(pkg);
                card.querySelector('.p-price').textContent = formatPrice(pkg.price);
                card.addEventListener('click', () => selectPackage(pkg.id));
                els.grid.appendChild(card);
            });
        }

        function selectPackage(id) {
            selectedPlanId = id;
            document.querySelectorAll('.package').forEach((el) => {
                el.classList.toggle('selected', Number(el.dataset.id) === Number(id));
            });
            setError(els.subscribeError, '');
        }

        function resetSubscribe() {
            selectedPlanId = null;
            els.subscribePhone.value = '';
            setError(els.subscribeError, '');
            document.querySelectorAll('.package').forEach((el) => el.classList.remove('selected'));
        }
        function resetVoucher() { els.voucherCode.value = ''; els.voucherPhone.value = ''; setError(els.voucherError, ''); }
        function resetRedeem() { els.redeemPhone.value = ''; setError(els.redeemError, ''); }

        // ---- subscribe ----
        async function submitSubscribe() {
            if (!selectedPlanId) { setError(els.subscribeError, 'Chagua kifurushi kwanza.'); return; }
            const phone = normalizePhone(els.subscribePhone.value);
            if (phone === null) { setError(els.subscribeError, phoneError(els.subscribePhone.value)); return; }
            setError(els.subscribeError, '');

            setBusy(els.subscribeSubmit, true);
            try {
                const result = await api('POST', '/api/v1/portal/' + PORTAL.subdomain + '/payments', {
                    internet_plan_id: selectedPlanId,
                    customer_phone: phone,
                    captive_session: PORTAL.session_token || null,
                });
                pollPayment(result.data.id);
            } catch (error) {
                setBusy(els.subscribeSubmit, false);
                setError(els.subscribeError, errorMessage(error));
            }
        }

        async function pollPayment(paymentId) {
            for (let attempt = 0; attempt < 90; attempt++) {
                await new Promise((resolve) => setTimeout(resolve, 4000));
                try {
                    const result = await api('GET', '/api/v1/portal/' + PORTAL.subdomain + '/payments/' + paymentId);
                    const payment = result.data;

                    if (payment.status === 'failed') {
                        setBusy(els.subscribeSubmit, false);
                        setError(els.subscribeError, 'Malipo yameshindikana. Tafadhali jaribu tena.');
                        return;
                    }
                    if (payment.next_action === 'open_gateway_auth_url' && payment.gateway_auth_url) {
                        goToGateway(payment.gateway_auth_url);
                        return;
                    }
                    if (payment.status === 'paid') {
                        setBusy(els.subscribeSubmit, false);
                        setError(els.subscribeError, 'Malipo yamekamilika. Tafadhali chagua kifurushi tena ili kuendelea.');
                        return;
                    }
                } catch (error) {
                    // keep polling through transient errors
                }
            }
            setBusy(els.subscribeSubmit, false);
            setError(els.subscribeError, 'Malipo hayajathibitishwa bado. Tafadhali jaribu tena.');
        }

        // ---- voucher ----
        async function submitVoucher() {
            const code = els.voucherCode.value.trim();
            if (!code) { setError(els.voucherError, 'Ingiza namba ya vocha.'); return; }
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

        // ---- redeem / continue subscription ----
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

        // ---- wire up ----
        document.querySelectorAll('[data-go]').forEach((button) => {
            button.addEventListener('click', () => setState(button.dataset.go));
        });
        document.querySelectorAll('[data-back]').forEach((button) => {
            button.addEventListener('click', () => setState('initial'));
        });
        els.subscribeSubmit.addEventListener('click', submitSubscribe);
        els.voucherSubmit.addEventListener('click', submitVoucher);
        els.redeemSubmit.addEventListener('click', submitRedeem);

        renderPackages();
        setState('initial');
    </script>
</body>
</html>
