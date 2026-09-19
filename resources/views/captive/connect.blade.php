@extends('captive.layout')

@section('content')
    <div class="card">
        <div class="brand">
            @if ($company->logo_url)
                <img src="{{ $company->logo_url }}" alt="{{ $company->name }}">
            @endif
            <div>
                <h1>{{ $company->name }}</h1>
                <p class="muted">
                    {{ $company->captive_portal_welcome_message ?: 'Choose a package to get online.' }}
                </p>
            </div>
        </div>

        @if ($router)
            <p class="muted" style="margin-top:12px">
                Router: <strong>{{ $router->name }}</strong>
                @if ($router->gateway_id)
                    ({{ $router->gateway_id }})
                @endif
            </p>
        @endif
    </div>

    @if ($authenticated && $gatewayAuthUrl)
        <div class="card">
            <div class="status">
                <strong>You're already connected</strong>
                Your access is active. Open the link below to finish Wi‑Fi authorization.
            </div>
            <a class="btn" href="{{ $gatewayAuthUrl }}">Connect to the internet</a>
        </div>
    @endif

    <div class="card">
        <h2 style="font-size:16px;margin:0 0 12px">Internet packages</h2>

        @forelse ($plans as $plan)
            <form method="POST" action="{{ route('captive.pay') }}" class="plan">
                @csrf
                <input type="hidden" name="subdomain" value="{{ $company->subdomain }}">
                <input type="hidden" name="internet_plan_id" value="{{ $plan->id }}">
                @if ($sessionToken)
                    <input type="hidden" name="captive_session" value="{{ $sessionToken }}">
                @endif
                @if ($router)
                    <input type="hidden" name="router" value="{{ $router->gateway_id ?? $router->id }}">
                @endif

                <div class="plan-head">
                    <span class="plan-name">
                        {{ $plan->name }}
                        @if ($plan->badge)
                            <span class="badge">{{ $plan->badge }}</span>
                        @endif
                    </span>
                    <span class="plan-price">{{ number_format((float) $plan->price, 0) }} {{ $plan->price_currency ?? 'TZS' }}</span>
                </div>

                @if ($plan->description)
                    <p class="muted">{{ $plan->description }}</p>
                @endif

                <p class="muted">
                    Duration:
                    {{ $plan->duration ? $plan->duration.' '.\Illuminate\Support\Str::lower($plan->duration_unit) : 'Unlimited' }}
                </p>

                <label>Phone number (mobile money)</label>
                <input type="tel" name="customer_phone" placeholder="07XXXXXXXX" required>

                <label>Name (optional)</label>
                <input type="text" name="customer_name" placeholder="Your name">

                <button type="submit">Pay &amp; connect</button>
            </form>
        @empty
            <p class="muted">No packages are available for this hotspot right now.</p>
        @endforelse
    </div>

    <div class="card">
        <details>
            <summary>Have a voucher code?</summary>
            <form method="POST" action="{{ route('captive.voucher') }}">
                @csrf
                <input type="hidden" name="subdomain" value="{{ $company->subdomain }}">
                @if ($sessionToken)
                    <input type="hidden" name="captive_session" value="{{ $sessionToken }}">
                @endif
                @if ($router)
                    <input type="hidden" name="router" value="{{ $router->gateway_id ?? $router->id }}">
                @endif

                <label>Voucher code</label>
                <input type="text" name="code" placeholder="123456" required>

                <label>Name</label>
                <input type="text" name="customer_name" placeholder="Your name" required>

                <label>Phone number</label>
                <input type="tel" name="customer_phone" placeholder="07XXXXXXXX" required>

                <button type="submit" class="btn secondary">Redeem voucher</button>
            </form>
        </details>
    </div>
@endsection
