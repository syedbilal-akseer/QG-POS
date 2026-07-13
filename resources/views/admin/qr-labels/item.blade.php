<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QR Labels — {{ $item->item_code }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; color: #111; }
        .page-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #111; }
        .page-header h1 { margin: 0; font-size: 18px; }
        .page-header .meta { font-size: 12px; color: #555; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .label {
            border: 1px solid #444; border-radius: 8px; padding: 14px;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            page-break-inside: avoid;
        }
        .label .qr   { width: 200px; height: 200px; background: #fff; padding: 6px; }
        .label .qr canvas, .label .qr img, .label .qr svg { display: block; width: 100% !important; height: 100% !important; }
        .label .item-code   { font-size: 14px; font-family: 'Courier New', monospace; font-weight: 700; }
        .label .item-desc   { font-size: 11px; color: #444; text-align: center; line-height: 1.3; max-width: 95%; }
        .label .level-row   { display: flex; gap: 6px; align-items: center; font-size: 11px; }
        .label .level-badge { padding: 2px 8px; border-radius: 999px; background: #1f2937; color: #fff; font-weight: 600; letter-spacing: .3px; }
        .label .uom         { font-family: 'Courier New', monospace; font-weight: 700; }
        .controls { margin-bottom: 16px; }
        .controls button { padding: 8px 16px; border: 1px solid #1f2937; background: #1f2937; color: #fff; border-radius: 6px; cursor: pointer; font-weight: 600; }
        @media print { .controls { display: none; } }
    </style>
</head>
<body>
    <div class="controls">
        <button onclick="window.print()">🖨️ Print</button>
        <button onclick="history.back()">← Back</button>
    </div>

    <div class="page-header">
        <h1>{{ $item->item_code }} — {{ $item->item_description }}</h1>
        <span class="meta">{{ $packings->count() }} packing level(s) · printed {{ now()->format('d M Y, h:i A') }}</span>
    </div>

    @if($packings->isEmpty())
        <p style="color:#888;font-style:italic">No packing levels configured for this item yet. Run the SC import first.</p>
    @else
        <div class="grid">
            @foreach($packings as $p)
                <div class="label">
                    <div class="qr" data-payload="{{ $p->barcode_payload }}"></div>
                    <div class="item-code">{{ $item->item_code }}</div>
                    <div class="item-desc">{{ $item->item_description }}</div>
                    <div class="level-row">
                        <span class="level-badge">{{ $p->level_code }}</span>
                        <span class="uom">{{ $p->uom_code }}</span>
                        @if($p->conversion_to_base !== null)
                            <span style="color:#666">· {{ rtrim(rtrim(number_format($p->conversion_to_base, 6), '0'), '.') }} {{ optional($packings->firstWhere('level', 1))->uom_code ?? 'base' }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- qrcode-generator: tiny zero-dep client-side QR encoder. --}}
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
        document.querySelectorAll('.qr[data-payload]').forEach(el => {
            const data = el.getAttribute('data-payload');
            // typeNumber=0 → auto-pick smallest, errorCorrection='M' → 15% recovery (good for warehouse wear).
            const qr = qrcode(0, 'M');
            qr.addData(data);
            qr.make();
            // 6px per cell @ 200px ≈ ~33 cells/200 — fine for QR up to ~v6.
            el.innerHTML = qr.createImgTag(6, 0);
        });
    </script>
</body>
</html>
