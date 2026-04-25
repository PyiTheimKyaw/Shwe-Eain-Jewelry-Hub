<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 24px; }
        .header { border-bottom: 2px solid #d4af37; padding-bottom: 10px; margin-bottom: 18px; }
        .brand-wrap { display: flex; align-items: center; gap: 12px; }
        .logo { height: 34px; width: 34px; object-fit: contain; }
        .brand { font-size: 22px; font-weight: 700; color: #8b6c14; }
        .sub { font-size: 12px; color: #475569; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .grid td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        .key { width: 34%; color: #334155; font-weight: 600; }
    </style>
</head>
<body>
    @include('reports.partials.header', ['branding' => $branding, 'subtitle' => 'Payment Verification Report'])

    <table class="grid">
        <tr><td class="key">Payment ID</td><td>{{ $payment->id }}</td></tr>
        <tr><td class="key">Order Number</td><td>{{ $payment->order?->order_number ?? '-' }}</td></tr>
        <tr><td class="key">Customer</td><td>{{ $payment->order?->customer?->name ?? '-' }}</td></tr>
        <tr><td class="key">Status</td><td>{{ strtoupper((string) $payment->status) }}</td></tr>
        <tr><td class="key">Amount MMK</td><td>{{ number_format((float) $payment->amount_mmk, 2) }}</td></tr>
        <tr><td class="key">Amount USD</td><td>{{ number_format((float) $amount_usd, 2) }}</td></tr>
        <tr><td class="key">Payment Channel</td><td>{{ $payment->paymentProfile?->name ?? '-' }}</td></tr>
        <tr><td class="key">Submitted At</td><td>{{ optional($payment->submitted_at)->format('Y-m-d H:i') ?? '-' }}</td></tr>
        <tr><td class="key">Verified At</td><td>{{ optional($payment->verified_at)->format('Y-m-d H:i') ?? '-' }}</td></tr>
        <tr><td class="key">Verified By</td><td>{{ $payment->verifiedBy?->name ?? '-' }}</td></tr>
    </table>
</body>
</html>

