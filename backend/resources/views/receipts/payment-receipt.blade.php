<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 24px; }
        .header { border-bottom: 2px solid #d4af37; padding-bottom: 10px; margin-bottom: 18px; }
        .brand { font-size: 24px; font-weight: 700; color: #8b6c14; }
        .sub { font-size: 12px; color: #475569; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .grid td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        .key { width: 38%; color: #334155; font-weight: 600; }
        .value { color: #0f172a; }
        .total { margin-top: 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .total h3 { margin: 0 0 6px 0; font-size: 14px; color: #8b6c14; }
        .total p { margin: 0; font-size: 18px; font-weight: 700; }
        .foot { margin-top: 22px; font-size: 11px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $branding_name }}</div>
        <div class="sub">Formal Payment Receipt</div>
    </div>

    <table class="grid">
        <tr><td class="key">Receipt Number</td><td class="value">{{ $receipt_number }}</td></tr>
        <tr><td class="key">Order Number</td><td class="value">{{ $order_number }}</td></tr>
        <tr><td class="key">Customer</td><td class="value">{{ $customer_name }}</td></tr>
        <tr><td class="key">Customer Email</td><td class="value">{{ $customer_email }}</td></tr>
        <tr><td class="key">Payment Type</td><td class="value">{{ strtoupper($payment_type) }}</td></tr>
        <tr><td class="key">Issued At</td><td class="value">{{ $issued_at->format('Y-m-d H:i') }}</td></tr>
    </table>

    <div class="total">
        <h3>Amount Paid</h3>
        <p>{{ number_format((float) $amount_mmk, 2) }} MMK</p>
        @if($amount_usd_snapshot !== null)
            <div class="sub">USD snapshot: {{ number_format((float) $amount_usd_snapshot, 2) }} USD</div>
        @endif
    </div>

    <div class="foot">
        This receipt is issued by {{ $branding_name }} and generated from the order/payment ledger snapshot.
    </div>
</body>
</html>

