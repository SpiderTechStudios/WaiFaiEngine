@extends('captive.layout')

@section('content')
    <div class="card">
        <div class="brand">
            @if ($company->logo_url)
                <img src="{{ $company->logo_url }}" alt="{{ $company->name }}">
            @endif
            <div>
                <h1>{{ $company->name }}</h1>
                <p class="muted">Hotspot access</p>
            </div>
        </div>
    </div>

    @if ($gatewayAuthUrl && $nextAction === 'open_gateway_auth_url')
        <div class="card">
            <div class="status">
                <strong>
                    @if ($voucher)
                        Voucher redeemed
                    @else
                        Payment received
                    @endif
                </strong>
                You are authorized. Continue to finish Wi‑Fi authorization and get online.
                @if ($package)
                    <p class="muted">Package: {{ $package['name'] ?? '' }}</p>
                @endif
            </div>
            <a class="btn" href="{{ $gatewayAuthUrl }}">Connect to the internet</a>
            <p class="muted" style="margin-top:8px">If nothing happens, tap the button above.</p>
        </div>
        <script>
            setTimeout(function () {
                window.location.href = @json($gatewayAuthUrl);
            }, 1200);
        </script>
    @elseif ($status === 'failed')
        <div class="card">
            <div class="status">
                <strong>Payment failed</strong>
                The mobile money request was not completed. Please try again.
            </div>
            <a class="btn" href="{{ route('captive.connect', array_filter([
                'subdomain' => $company->subdomain,
                'router' => $router,
                'session' => $sessionToken,
            ])) }}">Try again</a>
        </div>
    @else
        <div class="card">
            <div class="status">
                <strong>Waiting for payment…</strong>
                Complete the mobile money request on your phone. This page refreshes automatically.
            </div>
        </div>
        <script>
            setTimeout(function () {
                window.location.reload();
            }, 5000);
        </script>
    @endif

    <div class="card">
        <a class="btn secondary" href="{{ route('captive.connect', array_filter([
            'subdomain' => $company->subdomain,
            'router' => $router,
            'session' => $sessionToken,
        ])) }}">Back to packages</a>
    </div>
@endsection
