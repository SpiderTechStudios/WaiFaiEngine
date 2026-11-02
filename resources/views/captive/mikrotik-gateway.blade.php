<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Connecting...</title>
</head>
<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:3rem auto;padding:0 1rem;text-align:center">
    <h1>Connecting you to the internet</h1>
    <p>Please wait while we sign you in...</p>

    <form id="mt-login" method="post" action="{{ $gatewayScheme }}://{{ $gatewayAddress }}:{{ $gatewayPort }}/login">
        <input type="hidden" name="username" value="{{ $username }}">
        <input type="hidden" name="password" value="{{ $password }}">
        <input type="hidden" name="dst" value="{{ $target }}">
        <input type="hidden" name="popup" value="1">
        <button type="submit" style="padding:.75rem 1.5rem;font-size:1rem">Continue</button>
    </form>

    <script>document.getElementById('mt-login').submit();</script>

    <p style="margin-top:2rem;font-size:.85rem;color:#666">
        If nothing happens, tap <strong>Continue</strong>.
        <a href="{{ $portalUrl }}">Back to the portal</a>
    </p>
</body>
</html>
