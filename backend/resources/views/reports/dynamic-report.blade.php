<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 24px; }
        .header { border-bottom: 2px solid #d4af37; padding-bottom: 10px; margin-bottom: 18px; }
        .brand-wrap { display: flex; align-items: center; gap: 12px; }
        .logo { height: 52px; width: auto; max-width: 140px; object-fit: contain; }
        .brand { font-size: 22px; font-weight: 700; color: #8b6c14; }
        .sub { font-size: 12px; color: #475569; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .grid td, .grid th { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; text-align: left; }
    </style>
</head>
<body>
    @include('reports.partials.header', ['branding' => $branding, 'subtitle' => $title])
    <p>Period: {{ $start->format('Y-m-d') }} to {{ $end->format('Y-m-d') }}</p>

    <table class="grid">
        <thead>
        <tr>
            @foreach($headings as $heading)
                <th>{{ $heading }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                @foreach($row as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>

