<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Summary Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 24px; }
        .header { border-bottom: 2px solid #d4af37; padding-bottom: 10px; margin-bottom: 18px; }
        .brand-wrap { display: flex; align-items: center; gap: 12px; }
        .logo { height: 34px; width: 34px; object-fit: contain; }
        .brand { font-size: 22px; font-weight: 700; color: #8b6c14; }
        .sub { font-size: 12px; color: #475569; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .grid td, .grid th { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; text-align: left; }
        .totals { margin-top: 14px; border: 1px solid #e2e8f0; padding: 12px; background: #f8fafc; }
        .totals p { margin: 4px 0; font-size: 13px; }
    </style>
</head>
<body>
    @include('reports.partials.header', ['branding' => $branding, 'subtitle' => 'Sales Summary'])
    <p>Period: {{ $start->format('Y-m-d H:i') }} to {{ $end->format('Y-m-d H:i') }}</p>

    <div class="totals">
        <p><strong>Total MMK:</strong> {{ number_format((float) $total_mmk, 2) }}</p>
        <p><strong>Total USD:</strong> {{ number_format((float) $total_usd, 2) }}</p>
        <p><strong>Confirmed Payments:</strong> {{ $rows->count() }}</p>
    </div>

    <table class="grid">
        <thead>
        <tr>
            <th>Payment ID</th>
            <th>Order ID</th>
            <th>Verified At</th>
            <th>Amount (MMK)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($rows as $payment)
            <tr>
                <td>{{ $payment->id }}</td>
                <td>{{ $payment->order_id }}</td>
                <td>{{ optional($payment->verified_at)->format('Y-m-d H:i') ?? '-' }}</td>
                <td>{{ number_format((float) $payment->amount_mmk, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>

