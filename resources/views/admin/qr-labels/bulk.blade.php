<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QR Labels — Bulk</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; color: #111; }
        .page-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #111; }
        .page-header h1 { margin: 0; font-size: 18px; }
        .item-section { margin-bottom: 24px; page-break-inside: avoid; }
        .item-section h2 { font-size: 14px; margin: 0 0 8px; padding: 6px 10px; background: #f3f4f6; border-radius: 4px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .label {
            border: 1px solid #444; border-radius: 6px; padding: 8px;
            display: flex; flex-direction: column; align-items: center; gap: 4px;
            page-break-inside: avoid;
        }
        .label .qr   { width: 140px; height: 140px; background: #fff; padding: 4px; }
        .label .qr img { display: block; width: 100% !important; height: 100% !important; }
        .label .item-code { font-size: 11px; font-family: 'Courier New', monospace; font-weight: 700; }
        .label .level-badge { padding: 1px 6px; font-size: 9px; border-radius: 999px; background: #1f2937; color: #fff; font-weight: 600; letter-spacing: .3px; }
        .label .uom { font-family: 'Courier New', monospace; font-weight: 700; font-size: 11px; }
        .controls { margin-bottom: 16px; }
        .controls button { padding: 8px 16px; border: 1px solid #1f2937; background: #1f2937; color: #fff; border-radius: 6px; cursor: pointer; font-weight: 600; }
        @media print { .controls { display: none; } }
    </style>
</head>
<body>
    <div class="controls">
        <button onclick="window.print()">🖨️ Print</button>
    </div>

    <div class="page-header">
        <h1>QR Labels — Bulk Sheet</h1>
        <span style="font-size:12px;color:#555">{{ $packings->count() }} item(s) · printed {{ now()->format('d M Y, h:i A') }}</span>
    </div>

    @forelse($packings as $itemCode => $rows)
        @php($item = $items[$itemCode] ?? null)
        <div class="item-section">
            <h2>{{ $itemCode }} — {{ optional($item)->item_description ?? 'Unknown item' }}</h2>
            <div class="grid">
                @foreach($rows as $p)
                    <div class="label">
                        <div class="qr" data-payload="{{ $p->barcode_payload }}"></div>
                        <div class="item-code">{{ $itemCode }}</div>
                        <div style="display:flex;gap:4px;align-items:center">
                            <span class="level-badge">L{{ $p->level }}</span>
                            <span class="uom">{{ $p->uom_code }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p style="color:#888;font-style:italic">No items found for the requested codes.</p>
    @endforelse

    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
        document.querySelectorAll('.qr[data-payload]').forEach(el => {
            const data = el.getAttribute('data-payload');
            const qr = qrcode(0, 'M');
            qr.addData(data);
            qr.make();
            el.innerHTML = qr.createImgTag(4, 0);
        });
    </script>
</body>
</html>
