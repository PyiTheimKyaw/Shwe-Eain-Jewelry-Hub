<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Order Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 24px; }
        .header { border-bottom: 2px solid #d4af37; padding-bottom: 10px; margin-bottom: 18px; }
        .brand-wrap { display: flex; align-items: center; gap: 12px; }
        .logo { height: 52px; width: auto; max-width: 140px; object-fit: contain; }
        .brand { font-size: 22px; font-weight: 700; color: #8b6c14; }
        .sub { font-size: 12px; color: #475569; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .grid td, .grid th { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; text-align: left; }
        .key { width: 30%; color: #334155; font-weight: 600; }
        .section-title { margin-top: 16px; margin-bottom: 8px; color: #8b6c14; font-size: 13px; font-weight: 700; }
    </style>
</head>
<body>
    @include('reports.partials.header', ['branding' => $branding, 'subtitle' => 'Order Transaction Report'])

    <table class="grid">
        <tr><td class="key">Order Number</td><td>{{ $order->order_number }}</td></tr>
        <tr><td class="key">Customer</td><td>{{ $order->customer?->name ?? '-' }}</td></tr>
        <tr><td class="key">Status</td><td>{{ strtoupper((string) $order->status) }}</td></tr>
        <tr><td class="key">Total MMK</td><td>{{ number_format((float) $order->total_mmk, 2) }}</td></tr>
        <tr><td class="key">Total USD</td><td>{{ number_format((float) ($order->total_usd_snapshot ?? 0), 2) }}</td></tr>
        <tr><td class="key">Created At</td><td>{{ optional($order->created_at)->format('Y-m-d H:i') }}</td></tr>
    </table>

    <div class="section-title">Order Items</div>
    <table class="grid">
        <thead>
        <tr>
            <th>Item</th>
            <th>Weight (g)</th>
            <th>Line Total (MMK)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->title_snapshot ?: ($item->product?->title ?? 'Custom item') }}</td>
                <td>{{ number_format((float) $item->weight_grams, 2) }}</td>
                <td>{{ number_format((float) $item->line_total_mmk, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>

