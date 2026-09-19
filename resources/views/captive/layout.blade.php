<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? ($company->name ?? 'Captive Portal') }}</title>
    @isset($refresh)
        <meta http-equiv="refresh" content="{{ $refresh }}">
    @endisset
    <style>
        :root { --brand: {{ $company->primary_color ?? '#2563eb' }}; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            line-height: 1.5;
        }
        .wrap { max-width: 560px; margin: 0 auto; padding: 24px 16px 48px; }
        .card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
            margin-bottom: 16px;
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand img { max-height: 44px; border-radius: 8px; }
        .brand h1 { font-size: 20px; margin: 0; }
        .muted { color: #64748b; font-size: 14px; margin: 4px 0 0; }
        .plan { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
        .plan.selected { border-color: var(--brand); }
        .plan-head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
        .plan-name { font-weight: 600; }
        .plan-price { font-weight: 700; color: var(--brand); white-space: nowrap; }
        .badge {
            display: inline-block; font-size: 11px; font-weight: 600; text-transform: uppercase;
            background: #eef2ff; color: #4338ca; border-radius: 999px; padding: 2px 8px; margin-left: 6px;
        }
        label { display: block; font-size: 13px; font-weight: 600; margin: 10px 0 4px; }
        input[type=text], input[type=tel], input[type=email] {
            width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px;
        }
        button, .btn {
            display: inline-block; width: 100%; text-align: center; text-decoration: none;
            background: var(--brand); color: #fff; border: 0; border-radius: 10px;
            padding: 13px 16px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 12px;
        }
        .btn.secondary { background: #0f172a; }
        .status { font-size: 15px; }
        .status strong { display: block; font-size: 18px; margin-bottom: 4px; }
        .error { color: #b91c1c; font-size: 14px; margin-top: 6px; }
        hr { border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0; }
        details summary { cursor: pointer; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        @yield('content')
    </div>
</body>
</html>
