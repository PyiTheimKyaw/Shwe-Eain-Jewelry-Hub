<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Final Receipt {{ $receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        .header { margin-bottom: 14px; }
        .brand-wrap { display: flex; align-items: center; gap: 12px; }
        .logo { height: 56px; width: auto; max-width: 160px; object-fit: contain; }
        .brand { font-size: 18px; font-weight: bold; color: #8a6d1a; }
        .subtle { color: #6b7280; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .meta td { padding: 4px 0; vertical-align: top; }
        .items { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .items th, .items td { border-bottom: 1px solid #e5e7eb; padding: 8px 6px; text-align: left; }
        .items th { background: #f9fafb; }
        .totals { margin-top: 14px; width: 100%; border-collapse: collapse; }
        .totals td { padding: 5px 0; }
        .totals .label { color: #4b5563; }
        .totals .value { text-align: right; font-weight: 600; }
        .stamp {
            margin-top: 20px;
            display: inline-block;
            border: 2px solid #059669;
            color: #059669;
            padding: 8px 16px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 1px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        @include('reports.partials.header', ['branding' => $branding_name, 'subtitle' => 'Official Final Receipt'])
        <table class="meta">
            <tr>
                <td><strong>Receipt #:</strong> {{ $receipt_number }}</td>
                <td><strong>Order #:</strong> {{ $order_number }}</td>
            </tr>
            <tr>
                <td><strong>Customer:</strong> {{ $customer_name }}</td>
                <td><strong>Email:</strong> {{ $customer_email }}</td>
            </tr>
            <tr>
                <td><strong>Issued at:</strong> {{ \Illuminate\Support\Carbon::parse($issued_at)->format('Y-m-d H:i') }}</td>
                <td><strong>Currency:</strong> {{ $currency_code }}</td>
            </tr>
            <tr>
                <td><strong>Payment Method:</strong> {{ $payment_method_label ?? 'Cash' }}</td>
                <td></td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th>Weight (g)</th>
                <th>Line total (MMK)</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($line_items ?? []) as $line)
                <tr>
                    <td>{{ $line['title'] ?? 'Item' }}</td>
                    <td>{{ number_format((float) ($line['weight_grams'] ?? 0), 2) }}</td>
                    <td>{{ number_format((float) ($line['line_total_mmk'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Total price</td>
            <td class="value">{{ number_format((float) ($order_total_mmk ?? 0), 2) }} MMK</td>
        </tr>
        <tr>
            <td class="label">Deposit paid (confirmed)</td>
            <td class="value">{{ number_format((float) ($deposit_paid_mmk ?? 0), 2) }} MMK</td>
        </tr>
        <tr>
            <td class="label">Final payment paid</td>
            <td class="value">{{ number_format((float) ($final_paid_mmk ?? 0), 2) }} MMK</td>
        </tr>
        <tr>
            <td class="label"><strong>This receipt payment amount</strong></td>
            <td class="value"><strong>{{ number_format((float) ($amount_mmk ?? 0), 2) }} MMK</strong></td>
        </tr>
    </table>

    <div class="stamp">FULLY PAID</div>
</body>
</html>

