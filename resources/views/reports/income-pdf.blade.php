@php
    $currency = $report['range']['currency'];
    $money = fn ($value) => number_format((float) $value, 0);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Income {{ $report['range']['from'] }} – {{ $report['range']['to'] }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        .totals { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .totals td { padding: 6px 8px; border: 1px solid #e5e7eb; }
        .totals td.label { color: #6b7280; width: 30%; }
        .totals td.value { font-weight: bold; text-align: right; width: 20%; }
        table.daily { width: 100%; border-collapse: collapse; }
        table.daily th, table.daily td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; text-align: right; }
        table.daily th:first-child, table.daily td:first-child { text-align: left; }
        table.daily th { background: #f3f4f6; }
        table.daily tr.total td { font-weight: bold; border-top: 2px solid #1f2937; }
    </style>
</head>
<body>
    <h1>{{ $company->name }} — Income report</h1>
    <div class="muted">
        {{ $report['range']['from'] }} to {{ $report['range']['to'] }}
        · Time zone: {{ $report['range']['timezone'] }}
        · Generated {{ $report['generated_at'] }}
    </div>

    <table class="totals">
        <tr>
            <td class="label">Gross ({{ $currency }})</td><td class="value">{{ $money($report['totals']['gross']) }}</td>
            <td class="label">Mobile money</td><td class="value">{{ $money($report['totals']['mobile_money']) }}</td>
        </tr>
        <tr>
            <td class="label">Fees</td><td class="value">{{ $money($report['totals']['fees']) }}</td>
            <td class="label">Vouchers</td><td class="value">{{ $money($report['totals']['vouchers']) }}</td>
        </tr>
        <tr>
            <td class="label">Net</td><td class="value">{{ $money($report['totals']['net']) }}</td>
            <td class="label">Paid out</td><td class="value">{{ $money($report['totals']['paid_out']) }}</td>
        </tr>
        <tr>
            <td class="label">Transactions</td><td class="value">{{ $report['counts']['paid'] }}</td>
            <td class="label">Average ticket</td><td class="value">{{ $money($report['counts']['avg_ticket']) }}</td>
        </tr>
    </table>

    <table class="daily">
        <thead>
            <tr>
                <th>Date</th>
                <th>Mobile money</th>
                <th>Vouchers</th>
                <th>Total</th>
                <th>Transactions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['daily'] as $day)
                <tr>
                    <td>{{ $day['date'] }}</td>
                    <td>{{ $money($day['mobile_money']) }}</td>
                    <td>{{ $money($day['vouchers']) }}</td>
                    <td>{{ $money($day['total']) }}</td>
                    <td>{{ $day['count'] }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>TOTAL</td>
                <td>{{ $money($report['totals']['mobile_money']) }}</td>
                <td>{{ $money($report['totals']['vouchers']) }}</td>
                <td>{{ $money($report['totals']['gross']) }}</td>
                <td>{{ $report['counts']['paid'] }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
