@php
    $logoPath = public_path('images/logo.png');
    $logoData = null;
    if (is_file($logoPath)) {
        $logoData = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
    }
@endphp
<div class="header">
    <div class="brand-wrap">
        @if ($logoData)
            <img src="{{ $logoData }}" alt="Logo" class="logo" />
        @endif
        <div>
            <div class="brand">{{ $branding ?? 'Shwe Eain Jewelry Hub' }}</div>
            <div class="sub">{{ $subtitle ?? 'Administrative Report' }}</div>
        </div>
    </div>
</div>

