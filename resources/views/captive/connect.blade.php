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
            --brand-dark: color-mix(in srgb, var(--brand) 78%, #000);
            --brand-soft: color-mix(in srgb, var(--brand) 9%, #ffffff);
            --brand-line: color-mix(in srgb, var(--brand) 26%, #ffffff);
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --bg: #eef2f7;
            --card: #ffffff;
            --danger: #b91c1c;
            --success: #047857;
            --radius: 18px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background:
                radial-gradient(120% 60% at 50% 0%, color-mix(in srgb, var(--brand) 22%, #ffffff) 0%, transparent 60%),
                var(--bg);
            color: var(--ink);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding: 18px 14px 40px;
        }
        .portal { width: 100%; max-width: 520px; }

        /* ---------- Hero ---------- */
        .hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            color: #fff;
            border-radius: 26px;
            padding: 22px 20px 20px;
            box-shadow: 0 24px 45px -24px color-mix(in srgb, var(--brand) 85%, transparent);
        }
        .hero::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -90px;
            top: -110px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .12);
        }
        .hero-top { display: flex; align-items: center; gap: 14px; position: relative; z-index: 1; }
        .brand-mark {
            flex: none;
            width: 60px;
            height: 60px;
            padding: 6px;
            border-radius: 18px;
            background: #ffffff;
            display: grid;
            place-items: center;
            overflow: hidden;
            box-shadow: 0 8px 18px rgba(0, 0, 0, .16);
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
        .hero-brand { min-width: 0; }
        .hero-brand h1 {
            font-size: 21px;
            line-height: 1.15;
            margin: 0;
            letter-spacing: -0.02em;
            color: #fff;
            word-break: break-word;
        }
        .router-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 6px;
            max-width: 100%;
            font-size: 11.5px;
            font-weight: 600;
            color: rgba(255, 255, 255, .92);
            background: rgba(255, 255, 255, .16);
            border-radius: 999px;
            padding: 3px 10px;
        }
        .router-chip svg { width: 12px; height: 12px; flex: none; }
        .welcome {
            position: relative;
            z-index: 1;
            margin: 16px 0 0;
            font-size: 15.5px;
            font-weight: 500;
            line-height: 1.55;
            color: rgba(255, 255, 255, .98);
        }
        .support {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .16);
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }
        .support svg { width: 15px; height: 15px; flex: none; margin-top: 2px; }
        .support-text { display: flex; flex-direction: column; gap: 1px; }
        .support-title { font-size: 13px; font-weight: 700; }
        .support-desc { font-size: 11px; font-weight: 500; opacity: .82; }

        /* ---------- Panels ---------- */
        .panel {
            background: var(--card);
            border: 1px solid rgba(226, 232, 240, .9);
            border-radius: 22px;
            padding: 20px;
            margin-top: 14px;
            box-shadow: 0 18px 40px -30px rgba(15, 23, 42, .55);
        }
        .panel-kicker {
            display: block;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--brand);
            margin-bottom: 4px;
        }
        .panel-title { font-size: 19px; margin: 0; letter-spacing: -0.01em; }
        .panel-sub { margin: 6px 0 0; font-size: 13px; color: var(--muted); }

        /* ---------- Action cards ---------- */
        .actions { display: grid; gap: 10px; margin-top: 16px; }
        .action-card {
            display: flex;
            align-items: center;
            gap: 13px;
            width: 100%;
            text-align: left;
            background: #fff;
            border: 1.5px solid var(--line);
            border-radius: 16px;
            padding: 13px 14px;
            font: inherit;
            color: inherit;
            cursor: pointer;
            transition: border-color .15s ease, box-shadow .15s ease, transform .05s ease;
        }
        .action-card:active { transform: scale(.995); }
        .action-card:hover { border-color: var(--brand-line); box-shadow: 0 10px 22px -18px rgba(15, 23, 42, .5); }
        .action-card.primary {
            border-color: var(--brand);
            background: var(--brand-soft);
        }
        .action-icon {
            flex: none;
            width: 44px;
            height: 44px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            background: var(--brand-soft);
            color: var(--brand);
        }
        .action-card.primary .action-icon { background: var(--brand); color: #fff; }
        .action-icon svg { width: 22px; height: 22px; }
        .action-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
        .action-title { font-weight: 700; font-size: 15px; }
        .action-desc { color: var(--muted); font-size: 12.5px; line-height: 1.35; }
        .action-arrow { flex: none; color: var(--muted); display: grid; place-items: center; }
        .action-arrow svg { width: 18px; height: 18px; }

        .help-note {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px dashed var(--line);
            font-size: 12.5px;
            color: var(--muted);
            text-align: center;
        }
        .help-note a { color: var(--brand); font-weight: 700; text-decoration: none; }

        /* ---------- Offer ---------- */
        .offer-banner {
            position: relative;
            margin-bottom: 16px;
            padding: 16px;
            border-radius: 16px;
            border: 1.5px dashed #fb923c;
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        }
        .offer-tag {
            display: inline-block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #fff;
            background: #ea580c;
            border-radius: 999px;
            padding: 2px 9px;
            margin-bottom: 8px;
        }
        .offer-title { margin: 0; font-size: 16px; font-weight: 800; color: #9a3412; }
        .offer-desc { margin: 4px 0 0; font-size: 13px; color: #7c2d12; }
        .offer-meta { margin: 6px 0 0; font-size: 12px; font-weight: 700; color: #c2410c; }

        /* ---------- Buttons ---------- */
        .btn {
            display: block;
            width: 100%;
            text-align: center;
            border: 0;
            border-radius: 14px;
            padding: 15px 16px;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: transform .05s ease, opacity .15s ease;
        }
        .btn:active { transform: scale(.995); }
        .btn:disabled { opacity: .65; cursor: default; }
        .btn-primary { background: var(--brand); color: #fff; box-shadow: 0 12px 22px -14px color-mix(in srgb, var(--brand) 90%, transparent); }
        .btn-outline { background: #fff; color: var(--brand); border: 2px solid var(--brand); }
        .btn-offer { background: #ea580c; color: #fff; margin-top: 12px; }

        /* ---------- Packages ---------- */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .package {
            position: relative;
            text-align: left;
            background: #fff;
            border: 1.5px solid var(--line);
            border-radius: 16px;
            padding: 14px;
            cursor: pointer;
            font: inherit;
            color: inherit;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .package:active { transform: scale(.995); }
        .package.selected {
            border-color: var(--brand);
            background: var(--brand-soft);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 16%, transparent);
        }
        .package.selected::after {
            content: "✓";
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--brand);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            display: grid;
            place-items: center;
        }
        .package .p-name { font-weight: 700; font-size: 15px; padding-right: 24px; }
        .package .p-desc { color: var(--muted); font-size: 12px; margin-top: 4px; }
        .package .p-meta { color: var(--muted); font-size: 12px; margin-top: 6px; }
        .package .p-price { margin-top: 8px; font-weight: 800; color: var(--brand); font-size: 17px; }
        .package .p-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: color-mix(in srgb, var(--brand) 14%, #fff);
            color: var(--brand);
            border-radius: 999px;
            padding: 2px 8px;
            margin-top: 4px;
        }

        /* ---------- Forms ---------- */
        .field { margin-top: 16px; }
        .field-label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; }
        .field-hint { font-size: 12px; color: var(--muted); margin: 6px 0 0; }
        input[type=tel], input[type=text] {
            width: 100%;
            padding: 14px 12px;
            font-size: 16px;
            border: 1.5px solid var(--line);
            border-radius: 13px;
            background: #fff;
            color: var(--ink);
        }
        input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand) 15%, transparent);
        }
        .error { color: var(--danger); font-size: 13px; margin: 10px 0 0; min-height: 0; }
        .empty {
            margin: 14px 0 0;
            padding: 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px dashed var(--line);
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }
        .back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(229, 57, 53, .12);
            border: 1px solid rgba(229, 57, 53, .25);
            border-radius: 10px;
            color: #c62828;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 14px;
            margin: 0 0 14px;
            cursor: pointer;
        }
        .back:active { transform: scale(.98); }
        .back svg { width: 16px; height: 16px; }
        .submit { margin-top: 16px; }

        .status-box {
            margin-top: 14px;
            padding: 13px 14px;
            border-radius: 13px;
            background: #f8fafc;
            border: 1px solid var(--line);
            font-size: 14px;
            color: var(--ink);
            line-height: 1.45;
        }
        .status-box strong { display: block; margin-bottom: 2px; }
        .status-box.ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        .status-box.wait { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
        .status-box .meta { margin-top: 6px; color: var(--muted); font-size: 12px; word-break: break-all; }

        /* ---------- Footer ---------- */
        .footer {
            text-align: center;
            font-size: 12px;
            color: var(--muted);
            margin-top: 18px;
            line-height: 1.4;
        }
        .footer a { color: var(--brand); font-weight: 700; text-decoration: none; }

        /* ---------- Overlay ---------- */
        .hidden { display: none !important; }
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .97);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
            z-index: 50;
        }
        .spinner {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 3px solid color-mix(in srgb, var(--brand) 25%, #fff);
            border-top-color: var(--brand);
            animation: spin .8s linear infinite;
            margin-bottom: 14px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (min-width: 560px) {
            .portal { max-width: 600px; }
            .hero { padding: 26px 24px 24px; }
            .panel { padding: 24px; }
            .grid { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
        }
    </style>
</head>
<body>
    <main class="portal">
        <header class="hero">
            <div class="hero-top">
                <div class="brand-mark">
                    <img src="{{ $portal['logo_url'] ?? asset('images/logo.png') }}" alt="{{ $portal['business_name'] }}">
                </div>
                <div class="hero-brand">
                    <h1>{{ $portal['business_name'] }}</h1>
                    @if (! empty($portal['router_name']))
                        <div class="router-chip">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M2.5 8.5a14 14 0 0 1 19 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M5.5 11.8a9.6 9.6 0 0 1 13 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M8.6 15.1a5.2 5.2 0 0 1 6.8 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                <circle cx="12" cy="18.5" r="1.2" fill="currentColor"/>
                            </svg>
                            {{ $portal['router_name'] }}
                        </div>
                    @endif
                </div>
            </div>

            <p class="welcome">{{ $portal['welcome_message'] }}</p>

            <a class="support" href="tel:{{ $portal['contact_phone'] }}">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6.6 3.8 4.9 3.5a1.6 1.6 0 0 0-1.8 1.3c-.6 5.9 3 11.7 8.9 13.4l1.7 1.4c.4.3 1 .3 1.4-.1l1.8-1.6a1.6 1.6 0 0 0 .1-2.3l-2-2.2a1.6 1.6 0 0 0-2.1-.2l-1 .8a10.8 10.8 0 0 1-4.4-4.9l.9-.9a1.6 1.6 0 0 0 .2-2.1l-1-1.3a1.6 1.6 0 0 0-1.4-.6Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                </svg>
                <span class="support-text">
                    <span class="support-title">Huduma kwa wateja · {{ $portal['contact_phone'] }}</span>
                    <span class="support-desc">Bonyeza hapa kupiga huduma kwa wateja</span>
                </span>
            </a>
        </header>

        {{-- INITIAL STATE --}}
        <section id="state-initial" class="panel">
            <div id="offerBanner" class="offer-banner hidden">
                <span class="offer-tag">Ofa maalum</span>
                <p class="offer-title" id="offerTitle"></p>
                <p class="offer-desc" id="offerDesc"></p>
                <p class="offer-meta" id="offerMeta"></p>
                <button type="button" class="btn btn-offer" data-go="offer">Chukua ofa sasa</button>
            </div>

            <span class="panel-kicker">Karibu</span>
            <h2 class="panel-title">Ungependa kuanza vipi?</h2>
            <p class="panel-sub">Chagua njia inayokufaa ili kupata intaneti.</p>

            <div class="actions">
                @if ($portal['allows_mobile_money'])
                    <button type="button" class="action-card primary" data-go="subscribe">
                        <span class="action-icon">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <rect x="6.5" y="2.5" width="11" height="19" rx="2.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M10.5 18.5h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="action-body">
                            <span class="action-title">Jiunge sasa</span>
                            <span class="action-desc">Lipa kwa M-Pesa, Tigo Pesa, Airtel Money au HaloPesa.</span>
                        </span>
                        <span class="action-arrow">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </button>
                @endif

                @if ($portal['allows_voucher'])
                    <button type="button" class="action-card" data-go="voucher">
                        <span class="action-icon">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M3.5 9.5V6.8c0-.7.6-1.3 1.3-1.3h14.4c.7 0 1.3.6 1.3 1.3v2.7a2.5 2.5 0 0 0 0 5v2.7c0 .7-.6 1.3-1.3 1.3H4.8c-.7 0-1.3-.6-1.3-1.3v-2.7a2.5 2.5 0 0 0 0-5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                <path d="M14 6v12" stroke="currentColor" stroke-width="1.6" stroke-dasharray="2 3" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="action-body">
                            <span class="action-title">Jiunge kwa vocha</span>
                            <span class="action-desc">Una namba ya vocha? Ijumuishe hapa.</span>
                        </span>
                        <span class="action-arrow">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </button>
                @endif

                <button type="button" class="action-card" data-go="redeem">
                    <span class="action-icon">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M20 12a8 8 0 1 1-2.4-5.7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            <path d="M20 4.5V9h-4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 8v4.5l3 1.7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="action-body">
                        <span class="action-title">Endeleza kifurushi</span>
                        <span class="action-desc">Tayari umelipia? Rejesha kifurushi chako kilichopo.</span>
                    </span>
                    <span class="action-arrow">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </button>
            </div>

            <div class="help-note">
                Huna uhakika wa kuchagua? Piga
                <a href="tel:{{ $portal['contact_phone'] }}">{{ $portal['contact_phone'] }}</a>
                kwa msaada.
            </div>
        </section>

        {{-- SUBSCRIBE STATE --}}
        <section id="state-subscribe" class="panel hidden">
            <button type="button" class="back" data-back>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m15 5-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Rudi nyuma
            </button>

            <span class="panel-kicker">Hatua 1</span>
            <h2 class="panel-title">Chagua kifurushi</h2>
            <p class="panel-sub">Bonyeza kifurushi unachokitaka, kisha ingiza namba yako ya simu.</p>

            <div id="packageGrid" class="grid"></div>
            <p id="subscribeEmpty" class="empty hidden">Hakuna vifurushi vinavyopatikana kwa sasa.</p>

            <div class="field">
                <label class="field-label" for="subscribePhone">Namba ya simu ya malipo</label>
                <input id="subscribePhone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Mfano: 0712 345 678">
                <p class="field-hint">Utapokea ombi la malipo kwenye simu hii.</p>
            </div>

            <p id="subscribeError" class="error"></p>
            <div id="subscribeStatus" class="status-box hidden"></div>
            <button type="button" id="subscribeSubmit" class="btn btn-primary submit">Endelea kulipa</button>
        </section>

        {{-- VOUCHER STATE --}}
        <section id="state-voucher" class="panel hidden">
            <button type="button" class="back" data-back>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m15 5-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Rudi nyuma
            </button>

            <span class="panel-kicker">Vocha</span>
            <h2 class="panel-title">Jiunge kwa vocha</h2>
            <p class="panel-sub">Ingiza namba ya vocha yako ili kupata intaneti.</p>

            <div class="field">
                <label class="field-label" for="voucherCode">Namba ya vocha</label>
                <input id="voucherCode" type="text" inputmode="numeric" autocomplete="off" placeholder="Ingiza namba ya vocha">
            </div>

            <div class="field">
                <label class="field-label" for="voucherPhone">Namba ya simu</label>
                <input id="voucherPhone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Mfano: 0712 345 678">
                <p class="field-hint">Tunatumia namba hii kukutambulisha.</p>
            </div>

            <p id="voucherError" class="error"></p>
            <button type="button" id="voucherSubmit" class="btn btn-primary submit">Tumia vocha</button>
        </section>

        {{-- REDEEM STATE --}}
        <section id="state-redeem" class="panel hidden">
            <button type="button" class="back" data-back>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m15 5-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Rudi nyuma
            </button>

            <span class="panel-kicker">Endeleza</span>
            <h2 class="panel-title">Endeleza kifurushi</h2>
            <p class="panel-sub">Umeshalipia awali? Ingiza namba yako ili kuendelea.</p>

            <div class="field">
                <label class="field-label" for="redeemPhone">Namba ya simu</label>
                <input id="redeemPhone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Mfano: 0712 345 678">
            </div>

            <p id="redeemError" class="error"></p>
            <button type="button" id="redeemSubmit" class="btn btn-primary submit">Endelea</button>
        </section>

        {{-- OFFER STATE --}}
        <section id="state-offer" class="panel hidden">
            <button type="button" class="back" data-back>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m15 5-7 7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Rudi nyuma
            </button>

            <span class="panel-kicker">Ofa</span>
            <h2 class="panel-title" id="offerStateTitle">Ofa ya bure</h2>
            <p class="panel-sub" id="offerStateDesc"></p>

            <div class="field">
                <label class="field-label" for="offerPhone">Namba ya simu</label>
                <input id="offerPhone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Mfano: 0712 345 678">
            </div>

            <p id="offerError" class="error"></p>
            <button type="button" id="offerSubmit" class="btn btn-primary submit">Chukua ofa</button>
        </section>

        <p class="footer">
            Powered by <a href="https://waifai.co.tz" rel="noopener">WaiFai</a>
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
            offer: document.getElementById('state-offer'),
            offerBanner: document.getElementById('offerBanner'),
            offerTitle: document.getElementById('offerTitle'),
            offerDesc: document.getElementById('offerDesc'),
            offerMeta: document.getElementById('offerMeta'),
            offerPhone: document.getElementById('offerPhone'),
            offerError: document.getElementById('offerError'),
            offerSubmit: document.getElementById('offerSubmit'),
            offerStateTitle: document.getElementById('offerStateTitle'),
            offerStateDesc: document.getElementById('offerStateDesc'),
            grid: document.getElementById('packageGrid'),
            empty: document.getElementById('subscribeEmpty'),
            subscribePhone: document.getElementById('subscribePhone'),
            subscribeError: document.getElementById('subscribeError'),
            subscribeStatus: document.getElementById('subscribeStatus'),
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

        const states = { initial: els.initial, subscribe: els.subscribe, voucher: els.voucher, redeem: els.redeem, offer: els.offer };
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
                resetOffer();
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
            setPaymentStatus('');
            document.querySelectorAll('.package').forEach((el) => el.classList.remove('selected'));
        }
        function resetVoucher() { els.voucherCode.value = ''; els.voucherPhone.value = ''; setError(els.voucherError, ''); }
        function resetRedeem() { els.redeemPhone.value = ''; setError(els.redeemError, ''); }
        function resetOffer() { els.offerPhone.value = ''; setError(els.offerError, ''); }

        function offerDurationText(offer) {
            if (!offer) return '';
            if (!offer.duration) return durationLabels[offer.duration_unit] || '';
            const unit = durationLabels[offer.duration_unit] || offer.duration_unit || '';
            return offer.duration + ' ' + unit;
        }

        function renderOffer() {
            const offer = PORTAL.offer;
            if (!offer || offer.claimed) {
                els.offerBanner.classList.add('hidden');
                return;
            }

            const duration = offerDurationText(offer);
            els.offerBanner.classList.remove('hidden');
            els.offerTitle.textContent = offer.title || 'Ofa ya bure';
            els.offerDesc.textContent = offer.description || '';
            els.offerDesc.classList.toggle('hidden', !offer.description);

            let meta = duration ? ('Muda: ' + duration) : '';
            if (offer.remaining_claims !== null && offer.remaining_claims !== undefined) {
                meta += (meta ? ' · ' : '') + ('Zimebaki nafasi ' + offer.remaining_claims);
            }
            els.offerMeta.textContent = meta;
            els.offerMeta.classList.toggle('hidden', !meta);

            els.offerStateTitle.textContent = offer.title || 'Ofa ya bure';
            els.offerStateDesc.textContent = (offer.description || '')
                + (duration ? ((offer.description ? ' — ' : '') + duration + ' bure') : '');
        }

        function setPaymentStatus(html, kind) {
            if (!html) {
                els.subscribeStatus.className = 'status-box hidden';
                els.subscribeStatus.innerHTML = '';
                return;
            }
            els.subscribeStatus.className = 'status-box ' + (kind || 'wait');
            els.subscribeStatus.innerHTML = html;
        }

        // ---- subscribe ----
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
                    'wait'
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
                            '<strong>Malipo yamefanikiwa.</strong><div>Kifurushi chako kiko tayari. Inakufungulia intaneti...</div>' +
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
                        'wait'
                    );
                } catch (error) {
                    // keep polling through transient errors
                }
            }
            hideOverlay();
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

        // ---- offer ----
        async function submitOffer() {
            const offer = PORTAL.offer;
            if (!offer) { setError(els.offerError, 'Hakuna ofa inayopatikana kwa sasa.'); return; }
            const phone = normalizePhone(els.offerPhone.value);
            if (phone === null) { setError(els.offerError, phoneError(els.offerPhone.value)); return; }
            setError(els.offerError, '');

            setBusy(els.offerSubmit, true);
            showOverlay('Inachukua ofa yako...');
            try {
                const result = await api('POST', '/api/v1/portal/' + PORTAL.subdomain + '/offers/claim', {
                    offer_id: offer.id,
                    customer_phone: phone,
                    customer_name: 'Mteja',
                    captive_session: PORTAL.session_token || null,
                    mac_address: PORTAL.client_mac || null,
                });
                const url = result.data && result.data.captive ? result.data.captive.gateway_auth_url : null;
                if (url) { goToGateway(url); return; }
                hideOverlay();
                setBusy(els.offerSubmit, false);
                setError(els.offerError, 'Ofa imechukuliwa. Tafadhali unganisha kifaa chako kwenye WiFi.');
            } catch (error) {
                hideOverlay();
                setBusy(els.offerSubmit, false);
                setError(els.offerError, errorMessage(error));
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
        els.offerSubmit.addEventListener('click', submitOffer);

        renderOffer();
        renderPackages();
        setState('initial');

        if (PORTAL.gateway_auth_url) {
            goToGateway(PORTAL.gateway_auth_url);
        }
    </script>
</body>
</html>
