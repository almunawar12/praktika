{{--
    PDF template rendered by dompdf. Inline styles are intentional here: dompdf
    has no Tailwind pipeline, so the "no custom CSS" UI rule does not apply to
    this print artefact.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset->title }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .meta { color: #555; font-size: 10px; margin-bottom: 16px; }
        .meta span { margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 7px; text-align: left; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
        tr:nth-child(even) td { background: #fafafa; }
        .empty { text-align: center; color: #777; padding: 24px; }
        .footer { margin-top: 18px; color: #777; font-size: 9px; }
    </style>
</head>
<body>
    <h1>{{ $dataset->title }}</h1>
    <div class="meta">
        <span><strong>Cakupan:</strong> {{ $dataset->scope }}</span>
        <span><strong>Dibuat oleh:</strong> {{ $dataset->requestedBy }}</span>
        <span><strong>Waktu:</strong> {{ $dataset->generatedAt }}</span>
    </div>

    @if (count($dataset->rows) === 0)
        <p class="empty">Tidak ada data untuk cakupan ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($dataset->columns as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($dataset->rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="footer">Total {{ count($dataset->rows) }} baris.</p>
    @endif
</body>
</html>
